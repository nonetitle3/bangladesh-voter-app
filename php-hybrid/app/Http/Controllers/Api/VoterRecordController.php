<?php

namespace App\Http\Controllers\Api;

use App\Models\VoterRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoterRecordController
{
    private const EXPORT_FIELDS = [
        'voter_id','serial_no','name','father_name','mother_name','birth_date',
        'gender','occupation','address','village','ward','union_name','upazila',
        'district','division','post_code','pdf_filename','page_number',
    ];

    /**
     * Apply the legacy Node API's partial-match filters, using a strict
     * allow-list for SQL identifiers and bound values for all user input.
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = VoterRecord::query();
        $like = static function (string $value): string {
            return '%'.str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], trim($value)).'%';
        };

        if ($request->filled('q')) {
            $term = $like((string) $request->query('q'));
            $query->where(function (Builder $nested) use ($term): void {
                foreach (['name','father_name','mother_name','voter_id','serial_no','district','upazila','union_name','ward','occupation','address','village','division','post_code','pdf_filename'] as $column) {
                    $nested->orWhere($column, 'like', $term);
                }
            });
        }

        $filters = [
            'name'=>'name',
            'father_name'=>'father_name',
            'mother_name'=>'mother_name',
            'voter_id'=>'voter_id',
            'district'=>'district',
            'upazila'=>'upazila',
            'union'=>'union_name',
            'union_name'=>'union_name',
            'ward'=>'ward',
            'occupation'=>'occupation',
            'division'=>'division',
            'village'=>'village',
            'address'=>'address',
            'serial_no'=>'serial_no',
            'post_code'=>'post_code',
            'pdf_filename'=>'pdf_filename',
        ];
        foreach ($filters as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, 'like', $like((string) $request->query($input)));
            }
        }

        if ($request->filled('gender')) {
            $query->where('gender', '=', trim((string) $request->query('gender')));
        }
        if ($request->filled('page_number')) {
            $query->where('page_number', '=', max(1, (int) $request->query('page_number')));
        }
        if ($request->filled('document_id')) {
            $query->where('document_id', '=', max(1, (int) $request->query('document_id')));
        }

        return $query;
    }

    public function index(Request $request)
    {
        $request->validate([
            'page'=>['sometimes','integer','min:1'],
            'page_size'=>['sometimes','integer','min:1','max:200'],
            'per_page'=>['sometimes','integer','min:1','max:200'],
            'page_number'=>['sometimes','integer','min:1'],
            'document_id'=>['sometimes','integer','min:1'],
            'gender'=>['sometimes','string','max:30'],
            'q'=>['sometimes','string','max:300'],
            'name'=>['sometimes','string','max:300'],
            'father_name'=>['sometimes','string','max:300'],
            'mother_name'=>['sometimes','string','max:300'],
            'voter_id'=>['sometimes','string','max:100'],
            'serial_no'=>['sometimes','string','max:100'],
            'district'=>['sometimes','string','max:200'],
            'upazila'=>['sometimes','string','max:200'],
            'union'=>['sometimes','string','max:200'],
            'union_name'=>['sometimes','string','max:200'],
            'ward'=>['sometimes','string','max:100'],
            'occupation'=>['sometimes','string','max:300'],
            'division'=>['sometimes','string','max:200'],
            'village'=>['sometimes','string','max:300'],
            'address'=>['sometimes','string','max:500'],
            'post_code'=>['sometimes','string','max:30'],
            'pdf_filename'=>['sometimes','string','max:255'],
        ]);
        $page = max(1, (int) $request->query('page', 1));
        $size = min(200, max(1, (int) $request->query('page_size', $request->query('per_page', 50))));
        $query = $this->filteredQuery($request);
        $total = (clone $query)->count();
        $records = $query->orderByDesc('id')
            ->forPage($page, $size)
            ->get(array_merge(self::EXPORT_FIELDS, ['id','document_id']));

        return response()->json([
            'records'=>$records,
            'total_count'=>$total,
            'page'=>$page,
            'page_size'=>$size,
            'total_pages'=>(int) ceil($total / $size),
        ]);
    }

    public function dashboard()
    {
        $totalRecords = VoterRecord::query()->count();
        $totalPdfs = \App\Models\Document::query()->count();

        $topDistricts = VoterRecord::query()
            ->selectRaw("COALESCE(NULLIF(district, ''), 'Unknown') AS name, COUNT(*) AS count")
            ->groupBy('district')->orderByDesc('count')->limit(8)->get();
        $topOccupations = VoterRecord::query()
            ->selectRaw("COALESCE(NULLIF(occupation, ''), 'Unknown') AS name, COUNT(*) AS count")
            ->groupBy('occupation')->orderByDesc('count')->limit(8)->get();
        $gender = VoterRecord::query()
            ->selectRaw("COALESCE(NULLIF(gender, ''), 'Unknown') AS name, COUNT(*) AS count")
            ->groupBy('gender')->orderByDesc('count')->get();

        return response()->json([
            'total_pdfs'=>$totalPdfs,
            'total_records'=>$totalRecords,
            'top_districts'=>$topDistricts,
            'top_occupations'=>$topOccupations,
            'gender_breakdown'=>$gender,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($request)->orderBy('id');
        $fields = self::EXPORT_FIELDS;

        return response()->streamDownload(function () use ($query, $fields): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $fields);
            $query->chunkById(1000, function ($rows) use ($out, $fields): void {
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($fields as $field) {
                        $value = (string) ($row->{$field} ?? '');
                        // Prevent spreadsheet formula execution when CSV is opened.
                        if ($value !== '' && preg_match('/^[=+@\-\t\r]/u', $value)) $value = "'".$value;
                        $values[] = $value;
                    }
                    fputcsv($out, $values);
                }
            }, 'id');
            fclose($out);
        }, 'voter-records.csv', [
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Cache-Control'=>'no-store, no-cache, must-revalidate',
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($request)->orderBy('id');
        $fields = self::EXPORT_FIELDS;

        return response()->streamDownload(function () use ($query, $fields): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, '<?xml version="1.0" encoding="UTF-8"?>');
            fwrite($out, '<?mso-application progid="Excel.Sheet"?>');
            fwrite($out, '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Voters"><Table>');
            $writeRow = static function ($values) use ($out): void {
                fwrite($out, '<Row>');
                foreach ($values as $value) {
                    $safe = htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
                    fwrite($out, '<Cell><Data ss:Type="String">'.$safe.'</Data></Cell>');
                }
                fwrite($out, '</Row>');
            };
            $writeRow($fields);
            $query->chunkById(500, function ($rows) use ($writeRow, $fields): void {
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($fields as $field) $values[] = $row->{$field} ?? '';
                    $writeRow($values);
                }
            }, 'id');
            fwrite($out, '</Table></Worksheet></Workbook>');
            fclose($out);
        }, 'voter-records.xls', [
            'Content-Type'=>'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control'=>'no-store, no-cache, must-revalidate',
        ]);
    }
}
