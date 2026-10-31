<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class ImportLegacySqlite extends Command
{
    protected $signature = 'voters:import-sqlite
        {source : Path to a consistent SQLite backup file}
        {--execute : Perform inserts; without this option the command is a dry run}
        {--chunk=500 : Rows per transaction batch}';

    protected $description = 'Safely import legacy users, documents and voter_records from a SQLite backup into MySQL';

    private array $tables = [
        'users' => ['id','username','email','password','role','is_active','created_at'],
        'documents' => ['id','filename','stored_path','page_count','status','ocr_used','error_msg','uploaded_at'],
        'voter_records' => ['id','document_id','voter_id','serial_no','name','father_name','mother_name','birth_date','gender','occupation','address','village','ward','union_name','upazila','district','division','post_code','pdf_filename','page_number','raw_text','confidence','created_at'],
    ];

    public function handle(): int
    {
        $source = realpath($this->argument('source'));
        if (!$source || !is_file($source) || !is_readable($source)) {
            $this->error('SQLite backup file not found or not readable.');
            return self::FAILURE;
        }
        if (strtolower(pathinfo($source, PATHINFO_EXTENSION)) !== 'sqlite' &&
            strtolower(pathinfo($source, PATHINFO_EXTENSION)) !== 'db') {
            $this->warn('Source file extension is not .sqlite or .db; proceeding only if it is a valid SQLite database.');
        }

        $chunk = max(1, min(5000, (int)$this->option('chunk')));
        try {
            $sqlite = new PDO('sqlite:file:'.$source.'?mode=ro', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $integrity = $sqlite->query('PRAGMA integrity_check')->fetchColumn();
            if ($integrity !== 'ok') throw new RuntimeException('SQLite integrity_check failed: '.(string)$integrity);

            $sourceHash = hash_file('sha256', $source);
            $this->info('Source SHA-256: '.$sourceHash);
            $this->info('Source: '.$source);
            $this->info('Mode: '.($this->option('execute') ? 'EXECUTE' : 'DRY RUN'));

            $counts = [];
            foreach ($this->tables as $table => $columns) {
                $exists = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name=".$sqlite->quote($table))->fetchColumn();
                if (!$exists) throw new RuntimeException("Required source table missing: {$table}");
                $sourceInfo = $sqlite->query("PRAGMA table_info(\"{$table}\")")->fetchAll(PDO::FETCH_ASSOC);
                $sourceColumns = array_column($sourceInfo, 'name');
                $missingColumns = array_values(array_diff($columns, $sourceColumns));
                $extraColumns = array_values(array_diff($sourceColumns, $columns));
                if ($missingColumns) throw new RuntimeException("Source {$table} is missing mapped columns: ".implode(',', $missingColumns));
                if ($extraColumns) throw new RuntimeException("Unmapped source columns found in {$table}: ".implode(',', $extraColumns).". Add explicit MySQL mappings before importing; refusing to silently drop data.");
                $counts[$table] = (int)$sqlite->query("SELECT COUNT(*) FROM \"{$table}\"")->fetchColumn();
                $this->line("{$table}: {$counts[$table]} source rows");
            }
            $orphans = (int)$sqlite->query('SELECT COUNT(*) FROM voter_records vr LEFT JOIN documents d ON d.id=vr.document_id WHERE vr.document_id IS NOT NULL AND d.id IS NULL')->fetchColumn();
            if ($orphans > 0) throw new RuntimeException("Source has {$orphans} voter_records with missing documents; repair/mapping review is required before import.");
            if (!$this->option('execute')) {
                $this->comment('Dry run only. No destination rows were written. Re-run with --execute after reviewing backups and counts.');
                return self::SUCCESS;
            }

            DB::connection()->getPdo();
            foreach ($this->tables as $table => $columns) {
                $sourceColumns = $sqlite->query("PRAGMA table_info(\"{$table}\")")->fetchAll(PDO::FETCH_ASSOC);
                $available = array_column($sourceColumns, 'name');
                $use = array_values(array_intersect($columns, $available));
                if (!in_array('id', $use, true)) throw new RuntimeException("Source {$table} table has no id column.");
                $missing = array_values(array_diff(array_intersect($columns, ['username','password','filename']), $use));
                if ($missing) throw new RuntimeException("Required source columns missing in {$table}: ".implode(',', $missing));

                $inserted = 0; $skipped = 0;
                $offset = 0;
                while (true) {
                    $stmt = $sqlite->prepare("SELECT \"".implode('","', $use)."\" FROM \"{$table}\" ORDER BY id LIMIT :limit OFFSET :offset");
                    $stmt->bindValue(':limit', $chunk, PDO::PARAM_INT);
                    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                    $stmt->execute();
                    $rows = $stmt->fetchAll();
                    if (!$rows) break;

                    DB::transaction(function () use ($table, $rows, &$inserted, &$skipped) {
                        foreach ($rows as $row) {
                            $existing = DB::table($table)->where('id', $row['id'])->first();
                            if ($existing) {
                                $current = (array)$existing;
                                foreach ($row as $column => $value) {
                                    $actual = $current[$column] ?? null;
                                    if ($value === null && $actual === null) continue;
                                    if (is_numeric($value) && is_numeric($actual)) {
                                        if (abs((float)$value - (float)$actual) > 0.00001) {
                                            throw new RuntimeException("Conflicting existing {$table}.{$column} at id {$row['id']}; refusing to overwrite.");
                                        }
                                    } elseif ((string)$value !== (string)$actual) {
                                        throw new RuntimeException("Conflicting existing {$table}.{$column} at id {$row['id']}; refusing to overwrite.");
                                    }
                                }
                                $skipped++;
                                continue;
                            }
                            DB::table($table)->insert($row);
                            $inserted++;
                        }
                    }, 3);
                    $offset += count($rows);
                    $this->line("{$table}: scanned {$offset}/{$counts[$table]}");
                }

                if ($table === 'users') DB::statement('ALTER TABLE users AUTO_INCREMENT = '.((int)DB::table('users')->max('id') + 1));
                if ($table === 'documents') DB::statement('ALTER TABLE documents AUTO_INCREMENT = '.((int)DB::table('documents')->max('id') + 1));
                if ($table === 'voter_records') DB::statement('ALTER TABLE voter_records AUTO_INCREMENT = '.((int)DB::table('voter_records')->max('id') + 1));

                $target = (int)DB::table($table)->count();
                $this->info("{$table}: inserted={$inserted}, skipped_existing_id={$skipped}, destination_total={$target}");
                DB::table('migration_audits')->insert([
                    'source_name'=>basename($source),'source_sha256'=>$sourceHash,'table_name'=>$table,
                    'source_count'=>$counts[$table],'inserted_count'=>$inserted,'skipped_count'=>$skipped,
                    'status'=>'completed','details'=>json_encode(['destination_total'=>$target,'mode'=>'sqlite-import']),
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            $this->info('Import completed. Source SQLite file was opened read-only and was not modified.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
