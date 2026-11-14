<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('voter_records', function (Blueprint $table): void {
            $table->index('serial_no', 'voter_serial_no_idx');
            $table->index('gender', 'voter_gender_idx');
        });
    }

    public function down(): void
    {
        Schema::table('voter_records', function (Blueprint $table): void {
            $table->dropIndex('voter_serial_no_idx');
            $table->dropIndex('voter_gender_idx');
        });
    }
};
