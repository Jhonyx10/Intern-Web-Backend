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
        Schema::create('student_placement_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();
            $table->foreignId('section_id')
                ->nullable()
                ->constrained('sections')
                ->nullOnDelete();
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();
            $table->foreignId('changed_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('effective_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_placement_histories');
    }
};
