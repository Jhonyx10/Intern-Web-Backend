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
        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->dateTime('time_in');
            $table->dateTime('time_out')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('verification_method')->default('facial_recognition');
            $table->decimal('face_match_score', 5, 2)->nullable();
            $table->text('device_info')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'time_in']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_logs');
    }
};
