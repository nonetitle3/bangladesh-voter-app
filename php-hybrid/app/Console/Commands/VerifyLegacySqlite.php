<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class VerifyLegacySqlite extends Command
{
    protected $signature = 'voters:verify-sqlite {source : Same consistent SQLite backup used for import} {--chunk=500}';
    protected $description = 'Compare legacy SQLite row counts, IDs and field values against the MySQL migration';

    private array $tables = [
        'users' => ['id','username','email','password','role','is_active','created_at'],
        'documents' => ['id','filename','stored_path','page_count','status','ocr_used','error_msg','uploaded_at'],
        'voter_records' => ['id','document_id','voter_id','serial_no','name','father_name','mother_name','birth_date','gender','occupation','address','village','ward','union_name','upazila','district','division','post_code','pdf_filename','page_number','raw_text','confidence','created_at'],
    ];

    public function handle(): int
    {
        $source = realpath($this->argument('source'));
        if (!$source || !is_file($source)) { $this->error('Source backup not found.'); return self::FAILURE; }
        try {
            $sqlite = new PDO('sqlite:file:'.$source.'?mode=ro', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            if ($sqlite->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new \RuntimeException('SQLite integrity_check failed.');
            $chunk = max(1, min(5000, (int)$this->option('chunk')));
            $failed = false;
            foreach ($this->tables as $table => $columns) {
                $sourceCount = (int)$sqlite->query("SELECT COUNT(*) FROM \"{$table}\"")->fetchColumn();
                $targetCount = (int)DB::table($table)->count();
                $this->line("{$table}: source={$sourceCount}, MySQL={$targetCount}");
                if ($sourceCount !== $targetCount) { $this->error("Count mismatch in {$table}"); $failed = true; }
                $info = $sqlite->query("PRAGMA table_info(\"{$table}\")")->fetchAll(PDO::FETCH_ASSOC);
                $use = array_values(array_intersect($columns, array_column($info,'name')));
                $offset = 0;
                while (true) {
                    $stmt = $sqlite->prepare("SELECT \"".implode('","',$use)."\" FROM \"{$table}\" ORDER BY id LIMIT :limit OFFSET :offset");
                    $stmt->bindValue(':limit',$chunk,PDO::PARAM_INT); $stmt->bindValue(':offset',$offset,PDO::PARAM_INT); $stmt->execute();
                    $rows = $stmt->fetchAll(); if (!$rows) break;
                    foreach ($rows as $row) {
                        $target = (array)(DB::table($table)->where('id',$row['id'])->first() ?? []);
                        if (!$target) { $this->error("Missing {$table}.id={$row['id']}"); $failed=true; continue; }
                        foreach ($row as $column=>$value) {
                            $actual = $target[$column] ?? null;
                            if ($value === null && $actual === null) continue;
                            if (is_numeric($value) && is_numeric($actual)) {
                                if (abs((float)$value-(float)$actual)>0.00001) { $this->error("Mismatch {$table}.{$column} id={$row['id']}"); $failed=true; break; }
                            } elseif ((string)$value !== (string)$actual) {
                                $this->error("Mismatch {$table}.{$column} id={$row['id']}"); $failed=true; break;
                            }
                        }
                    }
                    $offset += count($rows);
                }
            }
            if ($failed) { $this->error('Verification FAILED. Do not cut over.'); return self::FAILURE; }
            $this->info('Verification PASSED: row counts, IDs and mapped fields match.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e); $this->error($e->getMessage()); return self::FAILURE;
        }
    }
}
