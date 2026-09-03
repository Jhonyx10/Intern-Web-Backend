<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Core Questionnaires / Templates (Reusable by any Course)
        Schema::create('evaluation_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Dynamic Questions / Form Fields
        Schema::create('evaluation_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_template_id')
                ->constrained('evaluation_templates')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('item_type'); // 'rating', 'text', 'textarea', 'single_choice', 'multiple_choice'
            $table->string('label');
            $table->json('options')->nullable(); // Holds rating scales, dropdown values, placeholders
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['evaluation_template_id', 'sort_order'], 'eval_item_tpl_sort_idx');
        });

        // 3. Junction Table: Many-to-Many (Templates <-> Courses)
        // Enables ONE evaluation template to be assigned to MANY courses
        Schema::create('course_evaluation_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('evaluation_template_id')
                ->constrained('evaluation_templates')
                ->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['course_id', 'evaluation_template_id'], 'course_eval_tpl_unique');
        });

        // 4. Student Evaluation Submissions
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('evaluation_template_id')->constrained('evaluation_templates')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete(); // Supervisor/Instructor
            $table->json('responses'); // JSON map of item_id => answer
            $table->decimal('computed_score', 5, 2)->nullable();
            $table->enum('status', ['pending', 'submitted'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('course_evaluation_template');
        Schema::dropIfExists('evaluation_template_items');
        Schema::dropIfExists('evaluation_templates');
    }
};