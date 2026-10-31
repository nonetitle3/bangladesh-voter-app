<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('documents', function (Blueprint $table) {
   $table->unsignedBigInteger('id')->autoIncrement()->primary();
   $table->string('filename');
   $table->text('stored_path')->nullable();
   $table->unsignedInteger('page_count')->nullable()->default(0);
   $table->string('status',30)->default('pending')->index();
   $table->boolean('ocr_used')->nullable()->default(false);
   $table->text('error_msg')->nullable();
   $table->timestamp('uploaded_at')->nullable()->index();
   $table->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('documents'); }
};