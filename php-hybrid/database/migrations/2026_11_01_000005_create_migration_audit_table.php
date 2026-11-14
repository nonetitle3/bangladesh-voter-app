<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('migration_audits', function (Blueprint $table) {
   $table->id();
   $table->string('source_name',255);
   $table->string('source_sha256',64);
   $table->string('table_name',100);
   $table->unsignedBigInteger('source_count');
   $table->unsignedBigInteger('inserted_count')->default(0);
   $table->unsignedBigInteger('skipped_count')->default(0);
   $table->string('status',30)->default('started');
   $table->json('details')->nullable();
   $table->timestamps();
   $table->index(['source_sha256','table_name']);
  });
 }
 public function down(): void { Schema::dropIfExists('migration_audits'); }
};