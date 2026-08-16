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
        Schema::create('ojt_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->date('absence_date');
            $table->enum('status', ['detected', 'justified'])->default('detected');
            $table->text('reason')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('proof_original_filename')->nullable();
            $table->unsignedBigInteger('proof_file_size')->nullable();
            $table->string('proof_mime_type')->nullable();
            $table->timestamp('justification_submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'absence_date']);
            $table->index(['student_id', 'absence_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ojt_absences');
    }
};
