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
        Schema::create('student_face_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->unique()
                ->constrained('students')
                ->cascadeOnDelete();
            $table->string('reference_image_path');
            $table->text('face_embedding')->nullable();
            $table->timestamp('enrolled_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_face_profiles');
    }
};
