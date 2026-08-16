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
        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')
                ->constrained('sections')
                ->cascadeOnDelete();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('deadline_at');
            $table->string('accepted_file_types')->default('pdf_and_word');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['section_id', 'is_active']);
            $table->index('deadline_at');
        });

        Schema::table('student_documents', function (Blueprint $table) {
            $table->foreignId('document_requirement_id')
                ->nullable()
                ->after('document_type_id')
                ->constrained('document_requirements')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_requirement_id');
        });

        Schema::dropIfExists('document_requirements');
    }
};
