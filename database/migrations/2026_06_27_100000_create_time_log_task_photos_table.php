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
        Schema::table('time_logs', function (Blueprint $table) {
            $table->enum('session_period', ['morning', 'afternoon'])
                ->nullable()
                ->after('student_id');
        });

        Schema::create('time_log_task_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_log_id')
                ->constrained('time_logs')
                ->cascadeOnDelete();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->default('image/jpeg');
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['time_log_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_log_task_photos');

        Schema::table('time_logs', function (Blueprint $table) {
            $table->dropColumn('session_period');
        });
    }
};
