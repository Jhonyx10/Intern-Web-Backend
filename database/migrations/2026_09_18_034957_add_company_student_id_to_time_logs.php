<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('time_logs', 'company_student_id')) {
                $table->foreignId('company_student_id')
                    ->nullable()
                    ->after('student_id')
                    ->constrained('company_student')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('time_logs', function (Blueprint $table) {
            if (Schema::hasColumn('time_logs', 'company_student_id')) {
                $table->dropConstrainedForeignId('company_student_id');
            }
        });
    }
};