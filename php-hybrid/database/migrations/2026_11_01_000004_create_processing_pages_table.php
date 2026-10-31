<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('processing_pages', function (Blueprint $table) {
   $table->id();
   $table->unsignedBigInteger('document_id');
   $table->unsignedInteger('page_number');
   $table->string('status',30)->default('pending');
   $table->boolean('ocr_used')->default(false);
   $table->longText('extracted_text')->nullable();
   $table->longText('normalized_text')->nullable();
   $table->decimal('confidence',7,4)->nullable();
   $table->text('error_msg')->nullable();
   $table->timestamp('started_at')->nullable();
   $table->timestamp('completed_at')->nullable();
   $table->timestamps();
   $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
   $table->unique(['document_id','page_number']);
   $table->index(['document_id','status']);
  });
 }
 public function down(): void { Schema::dropIfExists('processing_pages'); }
};