<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::table('documents', function (Blueprint $table) {
   $table->uuid('upload_batch_id')->nullable()->after('id');
   $table->index('upload_batch_id');
   $table->foreign('upload_batch_id')->references('id')->on('upload_batches')->nullOnDelete();
  });
 }
 public function down(): void {
  Schema::table('documents', function (Blueprint $table) {
   $table->dropForeign(['upload_batch_id']);
   $table->dropIndex(['upload_batch_id']);
   $table->dropColumn('upload_batch_id');
  });
 }
};
