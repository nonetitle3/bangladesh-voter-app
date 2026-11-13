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
  if ($base === '' || $token === '') throw new RuntimeException('Python worker URL or token is not configured');

  $document->forceFill(['status'=>'processing','error_msg'=>null])->save();
  try {
   $client = Http::acceptJson()->withToken($token)->connectTimeout(15)->timeout(30);
   $accepted = $client->post($base.'/internal/process', ['document_id'=>$document->id]);
   if (!$accepted->successful()) {
    throw new RuntimeException('Python worker rejected the job (HTTP '.$accepted->status().'): '.substr($accepted->body(),0,500));
   }
   $initial = $accepted->json() ?? [];
   if (($initial['status'] ?? '') === 'completed') {
    $document->refresh();
    $this->refreshBatch($document->upload_batch_id);
    return;
   }

   $deadline = time() + (int) config('services.python_worker.timeout',1500);
   do {
    sleep(5);
    $statusResponse = $client->get($base.'/internal/status/'.$document->id);
    if (!$statusResponse->successful()) {
     throw new RuntimeException('Python worker status request failed (HTTP '.$statusResponse->status().')');
    }
    $status = $statusResponse->json() ?? [];
    $state = $status['status'] ?? 'unknown';
    if ($state === 'completed') {
     $document->refresh();
     $this->refreshBatch($document->upload_batch_id);
     return;
    }
    if ($state === 'failed') {
     throw new RuntimeException('PDF processing failed: '.substr((string)($status['error'] ?? 'Unknown worker error'),0,1000));
    }
    if (time() >= $deadline) throw new RuntimeException('Timed out waiting for Python PDF worker');
   } while (true);
  } catch (ConnectionException $e) {
   throw new RuntimeException('Could not connect to Python PDF worker: '.$e->getMessage(),0,$e);
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
