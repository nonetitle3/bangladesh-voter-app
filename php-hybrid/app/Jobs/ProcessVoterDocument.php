<?php
namespace App\Jobs;

use App\Models\Document;
use App\Models\UploadBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ProcessVoterDocument implements ShouldQueue
{
 use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

 public int $tries = 3;
 public int $timeout = 1600;
 public array $backoff = [30, 120, 300];

 public function __construct(public int $documentId) {}

 public function handle(): void
 {
  $document = Document::query()->findOrFail($this->documentId);
  if ($document->status === 'completed') {
   $this->refreshBatch($document->upload_batch_id);
   return;
  }
  $base = rtrim((string) config('services.python_worker.url'), '/');
  $token = (string) config('services.python_worker.token');
  if ($base === '' || $token === '') {
   throw new RuntimeException('Python worker URL or token is not configured');
  }
  $document->forceFill(['status'=>'processing','error_msg'=>null])->save();

  try {
   $response = Http::acceptJson()
    ->withToken($token)
    ->connectTimeout(15)
    ->timeout((int) config('services.python_worker.timeout', 1500))
    ->post($base.'/internal/process', ['document_id'=>$document->id]);

   if (!$response->successful()) {
    throw new RuntimeException('Python worker returned HTTP '.$response->status().': '.substr($response->body(),0,500));
   }
   $result = $response->json() ?? [];
   if (($result['status'] ?? '') !== 'completed') {
    throw new RuntimeException('Python worker did not complete document processing: '.json_encode($result));
   }
   $document->refresh();
   $this->refreshBatch($document->upload_batch_id);
  } catch (ConnectionException $e) {
   throw new RuntimeException('Could not connect to Python PDF worker: '.$e->getMessage(), 0, $e);
  }
 }

 public function failed(Throwable $exception): void
 {
  $document = Document::query()->find($this->documentId);
  if ($document) {
   $document->forceFill(['status'=>'failed','error_msg'=>substr($exception->getMessage(),0,4000)])->save();
   $this->refreshBatch($document->upload_batch_id);
  }
 }

 private function refreshBatch(?string $batchId): void
 {
  if (!$batchId) return;
  $batch = UploadBatch::query()->with('documents')->find($batchId);
  if (!$batch) return;
  $docs = $batch->documents;
  $total = $docs->count();
  $completed = $docs->where('status','completed')->count();
  $failed = $docs->where('status','failed')->count();
  $finished = $completed + $failed;
  $records = $docs->sum(fn($doc) => $doc->voterRecords()->count());
  $batch->forceFill([
   'processed'=>$finished,'records'=>$records,'failures'=>$failed,
   'status'=>$finished >= $total ? ($failed ? 'completed_with_errors' : 'completed') : 'processing',
  ])->save();
 }
}
