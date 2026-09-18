<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_documents', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('document_requirement_id');

            $table->unique(
                ['student_id', 'document_requirement_id', 'period_start'],
                'student_doc_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('student_documents', function (Blueprint $table) {
            $table->dropUnique('student_doc_period_unique');
            $table->dropColumn('period_start');
        });
    }
};
