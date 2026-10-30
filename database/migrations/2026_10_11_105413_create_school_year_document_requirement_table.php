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
        Schema::create('school_year_document_requirement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_requirement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_year_id', 'document_requirement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_year_document_requirement');
    }
};
