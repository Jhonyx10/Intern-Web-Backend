<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ojt_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comments')->nullable();
            $table->date('evaluation_date')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['supervisor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ojt_evaluations');
    }
};
