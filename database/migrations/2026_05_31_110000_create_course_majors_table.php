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
        Schema::create('course_majors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('program_head_name')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['course_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_majors');
    }
};
