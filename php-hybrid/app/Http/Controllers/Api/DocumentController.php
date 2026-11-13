<?php
namespace App\Http\Controllers\Api;

use App\Jobs\ProcessVoterDocument;
use App\Models\Document;
use App\Models\UploadBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController
{
 public function upload(Request $request)
 {
  $maxFiles = max(1,min(100,(int) env('PDF_MAX_FILES_PER_UPLOAD',100)));
  $maxKb = max(1024,(int) env('PDF_MAX_FILE_MB',50)) * 1024;
  $data = $request->validate([
   'files'=>['required','array','min:1','max:'.$maxFiles],
   'files.*'=>['required','file','mimes:pdf','mimetypes:application/pdf','max:'.$maxKb],
  ]);

  $diskName = (string) config('filesystems.default');
  if (!in_array($diskName,['s3','pdf_local'],true)) {
   return response()->json(['error'=>'PDF_STORAGE_DISK must be s3 or pdf_local'],500);
  }
  $disk = Storage::disk($diskName);
  if ($diskName === 's3' && !config('filesystems.disks.s3.bucket')) {
   return response()->json(['error'=>'Private S3 PDF storage is not configured'],500);
  }

  $batch = UploadBatch::query()->create([
   'id'=>(string) Str::uuid(),'total'=>count($data['files']),
   'processed'=>0,'records'=>0,'failures'=>0,'status'=>'queued',
  ]);
  $created = [];
  $uploadedKeys = [];
  try {
   foreach ($data['files'] as $file) {
    $original = $file->getClientOriginalName();
    $safeName = preg_replace('/[^A-Za-z0-9._\-\x{0980}-\x{09FF}]/u','_',basename($original)) ?: 'document.pdf';
    if (!str_ends_with(strtolower($safeName),'.pdf')) $safeName .= '.pdf';
    $key = 'voter-pdfs/'.now()->format('Y/m').'/'.Str::uuid().'-'.$safeName;
    $stream = fopen($file->getRealPath(),'rb');
    try {
     $stored = $disk->put($key,$stream,['visibility'=>'private']);
    } finally {
     if (is_resource($stream)) fclose($stream);
    }
    if (!$stored) throw new \RuntimeException('Failed to store uploaded PDF');
    $storedPath = $diskName === 's3'
      ? 's3://'.config('filesystems.disks.s3.bucket').'/'.$key
      : $disk->path($key);

    $doc = Document::query()->create([
     'upload_batch_id'=>$batch->id,'filename'=>$original,'stored_path'=>$storedPath,
     'page_count'=>0,'status'=>'pending','ocr_used'=>false,
     'uploaded_at'=>now()->toIso8601String(),
    ]);
    $created[] = $doc;
   }
  } catch (\Throwable $e) {
   foreach ($created as $doc) {
    try { $this->deleteStoredFile($doc); $doc->delete(); } catch (\Throwable $ignored) {}
   }
   $batch->delete();
   return response()->json(['error'=>'PDF upload failed','detail'=>$e->getMessage()],500);
  }

  foreach ($created as $doc) ProcessVoterDocument::dispatch((int)$doc->id);
  $batch->forceFill(['status'=>'queued'])->save();
  return response()->json([
   'success'=>true,'jobId'=>$batch->id,'total'=>count($created),
   'documents'=>array_map(fn($doc)=>['id'=>$doc->id,'filename'=>$doc->filename,'status'=>$doc->status],$created),
  ],202);
 }

 public function batch(string $id)
 {
  $batch = UploadBatch::query()->with('documents')->find($id);
  if (!$batch) return response()->json(['error'=>'Job not found'],404);
  $docs = $batch->documents;
  return response()->json([
   'jobId'=>$batch->id,'total'=>$batch->total,'processed'=>$batch->processed,
   'records'=>$batch->records,'failures'=>$batch->failures,'status'=>$batch->status,
   'documents'=>$docs->map(fn($d)=>['id'=>$d->id,'filename'=>$d->filename,'status'=>$d->status,'page_count'=>$d->page_count,'error'=>$d->error_msg])->values(),
  ]);
 }

 public function index(Request $request)
 {
  $query = Document::query()->withCount(['voterRecords','pages'])->orderByDesc('id');
  if ($request->filled('status')) $query->where('status',$request->string('status')->toString());
  if ($request->filled('q')) $query->where('filename','like','%'.$request->string('q')->toString().'%');
  $page = $query->paginate(min(100,max(1,(int)$request->query('page_size',25))));
  return response()->json($page);
 }

 public function show(int $id)
 {
  $doc = Document::query()->withCount(['voterRecords','pages'])->find($id);
  if (!$doc) return response()->json(['error'=>'Document not found'],404);
  return response()->json(['document'=>$doc]);
 }

 public function pages(int $id)
 {
  $doc = Document::query()->find($id);
  if (!$doc) return response()->json(['error'=>'Document not found'],404);
  return response()->json([
   'document_id'=>$id,'page_count'=>$doc->page_count,
   'pages'=>$doc->pages()->orderBy('page_number')->get([
    'id','page_number','status','ocr_used','confidence','error_msg','started_at','completed_at'
   ]),
  ]);
 }

 public function retry(int $id)
 {
  $doc = Document::query()->find($id);
  if (!$doc) return response()->json(['error'=>'Document not found'],404);
  if ($doc->status === 'completed') return response()->json(['error'=>'Completed documents are not reprocessed'],409);
  if (!in_array($doc->status,['failed','pending'],true)) return response()->json(['error'=>'Document is already processing'],409);
  $doc->forceFill(['status'=>'pending','error_msg'=>null])->save();
  ProcessVoterDocument::dispatch((int)$doc->id);
  return response()->json(['success'=>true,'document_id'=>$doc->id,'status'=>'queued'],202);
 }

 public function download(int $id)
 {
  $doc = Document::query()->find($id);
  if (!$doc) return response()->json(['error'=>'Document not found'],404);
  [$disk,$key] = $this->resolveStoredPath($doc);
  if (!$disk->exists($key)) return response()->json(['error'=>'Stored PDF not found'],404);
  return $disk->download($key,basename($doc->filename));
 }

 private function resolveStoredPath(Document $doc): array
 {
  $path = (string)$doc->stored_path;
  if (str_starts_with($path,'s3://')) {
   $parts = parse_url($path);
   $bucket = $parts['host'] ?? '';
   $key = ltrim($parts['path'] ?? '','/');
   if ($bucket !== (string)config('filesystems.disks.s3.bucket') || $key === '' || str_contains($key,'..')) {
    abort(422,'Invalid private PDF object reference');
   }
   return [Storage::disk('s3'),$key];
  }
  $root = realpath((string)env('PDF_STORAGE_PATH','/var/data/pdfs'));
  $resolved = realpath($path);
  if (!$root || !$resolved || !str_starts_with($resolved,$root.DIRECTORY_SEPARATOR) || strtolower(pathinfo($resolved,PATHINFO_EXTENSION)) !== 'pdf') {
   abort(422,'Invalid local PDF path');
  }
  return [Storage::build(['driver'=>'local','root'=>$root]),substr($resolved,strlen($root)+1)];
 }

 private function deleteStoredFile(Document $doc): void
 {
  [$disk,$key] = $this->resolveStoredPath($doc);
  $disk->delete($key);
 }
}
