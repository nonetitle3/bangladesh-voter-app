<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('processing_page_records', function (Blueprint $table) {
   $table->id();
   $table->unsignedBigInteger('processing_page_id');
   $table->unsignedBigInteger('voter_record_id')->unique();
   $table->timestamps();
   $table->foreign('processing_page_id')->references('id')->on('processing_pages')->cascadeOnDelete();
   $table->foreign('voter_record_id')->references('id')->on('voter_records')->cascadeOnDelete();
   $table->index('processing_page_id');
  });
 }
 public function down(): void { Schema::dropIfExists('processing_page_records'); }
};
