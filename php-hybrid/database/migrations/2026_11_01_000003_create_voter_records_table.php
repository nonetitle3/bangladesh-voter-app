<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('voter_records', function (Blueprint $table) {
   $table->unsignedBigInteger('id')->autoIncrement()->primary();
   $table->unsignedBigInteger('document_id')->nullable();
   $table->string('voter_id',100)->nullable();
   $table->string('serial_no',100)->nullable();
   $table->text('name')->nullable();
   $table->text('father_name')->nullable();
   $table->text('mother_name')->nullable();
   $table->string('birth_date',40)->nullable();
   $table->string('gender',30)->nullable();
   $table->text('occupation')->nullable();
   $table->text('address')->nullable();
   $table->text('village')->nullable();
   $table->string('ward',100)->nullable();
   $table->text('union_name')->nullable();
   $table->text('upazila')->nullable();
   $table->text('district')->nullable();
   $table->text('division')->nullable();
   $table->string('post_code',30)->nullable();
   $table->text('pdf_filename')->nullable();
   $table->unsignedInteger('page_number')->nullable();
   $table->longText('raw_text')->nullable();
   $table->double('confidence')->nullable()->default(0);
   $table->text('created_at')->nullable();
   $table->timestamp('updated_at')->nullable();
   $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
   $table->index('voter_id');
   $table->index(['document_id','page_number'], 'voter_source_page_idx');
  });
 }
 public function down(): void { Schema::dropIfExists('voter_records'); }
};