<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::create('users', function (Blueprint $table) {
   $table->unsignedBigInteger('id')->autoIncrement()->primary();
   $table->string('username',100)->unique();
   $table->string('email')->nullable();
   $table->string('password');
   $table->string('role',30)->default('viewer')->index();
   $table->boolean('is_active')->default(true)->index();
   $table->timestamp('created_at')->nullable();
   $table->timestamp('updated_at')->nullable();
  });
 }
 public function down(): void { Schema::dropIfExists('users'); }
};