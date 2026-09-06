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
            $table->dropConstrainedForeignId('document_type_id');
        });

        Schema::table('document_requirements', function (Blueprint $table) {
            $table->foreignId('document_type_id')
                ->nullable()
                ->after('section_id')
                ->constrained('document_types')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_type_id');
        });

        Schema::table('student_documents', function (Blueprint $table) {
            $table->foreignId('document_type_id')
                ->nullable()
                ->constrained('document_types')
                ->restrictOnDelete();
        });
    }
};
