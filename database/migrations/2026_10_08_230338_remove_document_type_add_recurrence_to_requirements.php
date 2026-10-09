<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add recurrence to document_requirements
        Schema::table('document_requirements', function (Blueprint $table) {
            $table->enum('recurrence', ['none', 'daily', 'weekly'])->default('none')->after('is_active');
        });

        // 2. Drop document_type_id FK from document_requirements
        Schema::table('document_requirements', function (Blueprint $table) {
            $table->dropForeign(['document_type_id']);
            $table->dropColumn('document_type_id');
        });

        // 3. Drop document_type_id from student_documents if it exists
        if (Schema::hasColumn('student_documents', 'document_type_id')) {
            Schema::table('student_documents', function (Blueprint $table) {
                $table->dropForeign(['document_type_id']);
                $table->dropColumn('document_type_id');
            });
        }

        // 4. Drop the document_types table
        Schema::dropIfExists('document_types');
    }

    public function down(): void
    {
        // Recreate document_types
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_required')->default(false);
            $table->enum('recurrence', ['none', 'daily', 'weekly'])->default('none');
            $table->timestamps();
        });

        // Restore document_type_id on document_requirements
        Schema::table('document_requirements', function (Blueprint $table) {
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->dropColumn('recurrence');
        });
    }
};
