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
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();
            $table->foreignId('school_year_id')
                ->constrained('school_years')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->foreignId('coordinator_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['course_id', 'school_year_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
