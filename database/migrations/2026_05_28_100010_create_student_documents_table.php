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
        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->foreignId('document_type_id')
                ->constrained('document_types')
                ->restrictOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->default('application/pdf');
            $table->timestamp('uploaded_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_documents');
    }
};
