<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_student', function (Blueprint $table) {
            if (!Schema::hasColumn('company_student', 'status')) {
                $table->string('status')->default('active')->after('course_id');
            }
        });

        // Only touch the FK/unique-index rework if it hasn't happened yet.
        // We detect this by checking whether the old composite unique index
        // still exists under its default Laravel-generated name.
        $indexExists = collect(
            DB::select("SHOW INDEX FROM company_student WHERE Key_name = 'company_student_company_id_student_id_unique'")
        )->isNotEmpty();

        if ($indexExists) {
            Schema::table('company_student', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropForeign(['student_id']);
                $table->dropUnique(['company_id', 'student_id']);
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('company_student', function (Blueprint $table) {
            if (Schema::hasColumn('company_student', 'status')) {
                $table->dropColumn('status');
            }
        });

        $indexExists = collect(
            DB::select("SHOW INDEX FROM company_student WHERE Key_name = 'company_student_company_id_student_id_unique'")
        )->isNotEmpty();

        if (!$indexExists) {
            Schema::table('company_student', function (Blueprint $table) {
                $table->unique(['company_id', 'student_id']);
            });
        }
    }
};