<?php
namespace Tests\Feature;

use App\Jobs\ProcessVoterDocument;
use App\Models\Document;
use App\Models\UploadBatch;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
 use RefreshDatabase;

 private string $token;

 protected function setUp(): void
 {
  parent::setUp();
  config(['voter_auth.jwt_secret'=>'phase5-test-secret-0123456789abcdef']);
  $admin=User::query()->create([
   'username'=>'phase5-admin','password'=>bcrypt('password-123456'),
   'role'=>'admin','is_active'=>true,'created_at'=>now()->toIso8601String(),
  ]);
  $this->token=JWT::encode([
   'iat'=>time(),'exp'=>time()+28800,'sub'=>(string)$admin->id,
   'username'=>$admin->username,'role'=>'admin',
  ],'phase5-test-secret-0123456789abcdef','HS256');
  config(['filesystems.default'=>'s3','filesystems.disks.s3.bucket'=>'test-private-bucket']);
  Storage::fake('s3');
 }

 public function test_admin_can_upload_multiple_pdfs_and_jobs_are_queued(): void
 {
  Queue::fake();
  $response=$this->withToken($this->token)->post('/api/admin/upload',[
   'files'=>[
    UploadedFile::fake()->create('one.pdf',20,'application/pdf'),
    UploadedFile::fake()->create('two.pdf',30,'application/pdf'),
   ],
  ]);
  $response->assertStatus(202)->assertJsonPath('total',2)->assertJsonPath('success',true);
  $this->assertDatabaseCount('documents',2);
  $this->assertDatabaseCount('upload_batches',1);
  Queue::assertPushed(ProcessVoterDocument::class,2);
  foreach(Document::query()->get() as $document) {
   $this->assertStringStartsWith('s3://test-private-bucket/voter-pdfs/',$document->stored_path);
   $this->assertSame('pending',$document->status);
  }
 }

 public function test_viewer_or_anonymous_user_cannot_upload(): void
 {
  $this->postJson('/api/admin/upload',['files'=>[UploadedFile::fake()->create('x.pdf',10,'application/pdf')]])->assertUnauthorized();
 }

 public function test_batch_status_includes_document_progress(): void
 {
  $batch=UploadBatch::query()->create(['id'=>(string)\Illuminate\Support\Str::uuid(),'total'=>1,'processed'=>1,'records'=>5,'failures'=>0,'status'=>'completed']);
  Document::query()->create(['upload_batch_id'=>$batch->id,'filename'=>'source.pdf','stored_path'=>'s3://test-private-bucket/source.pdf','page_count'=>3,'status'=>'completed','ocr_used'=>true,'uploaded_at'=>now()->toIso8601String()]);
  $this->withToken($this->token)->getJson('/api/admin/upload/'.$batch->id)
   ->assertOk()->assertJsonPath('status','completed')->assertJsonPath('records',5)
   ->assertJsonPath('documents.0.page_count',3);
 }

 public function test_download_uses_private_storage_and_admin_authentication(): void
 {
  Storage::disk('s3')->put('voter-pdfs/source.pdf','%PDF-1.4 sample');
  $doc=Document::query()->create([
   'filename'=>'source.pdf','stored_path'=>'s3://test-private-bucket/voter-pdfs/source.pdf',
   'page_count'=>1,'status'=>'completed','ocr_used'=>false,'uploaded_at'=>now()->toIso8601String(),
  ]);
  $this->withToken($this->token)->get('/api/admin/documents/'.$doc->id.'/download')->assertOk();
 }

 public function test_failed_document_can_be_queued_for_retry_but_completed_one_cannot(): void
 {
  Queue::fake();
  $failed=Document::query()->create(['filename'=>'failed.pdf','stored_path'=>'s3://test-private-bucket/failed.pdf','status'=>'failed','uploaded_at'=>now()->toIso8601String()]);
  $this->withToken($this->token)->postJson('/api/admin/documents/'.$failed->id.'/retry')
   ->assertStatus(202)->assertJsonPath('status','queued');
  Queue::assertPushed(ProcessVoterDocument::class);
  $done=Document::query()->create(['filename'=>'done.pdf','stored_path'=>'s3://test-private-bucket/done.pdf','status'=>'completed','uploaded_at'=>now()->toIso8601String()]);
  $this->withToken($this->token)->postJson('/api/admin/documents/'.$done->id.'/retry')->assertStatus(409);
 }
}
