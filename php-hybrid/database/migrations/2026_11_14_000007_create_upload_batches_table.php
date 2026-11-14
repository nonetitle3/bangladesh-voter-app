<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('upload_batches', function (Blueprint $table) {
   $table->uuid('id')->primary();
   $table->unsignedInteger('total')->default(0);
   $table->unsignedInteger('processed')->default(0);
   $table->unsignedInteger('records')->default(0);
   $table->unsignedInteger('failures')->default(0);
   $table->string('status',30)->default('queued')->index();
   $table->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('upload_batches'); }
};
