<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ojt_evaluation_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')
                ->constrained('sections')
                ->cascadeOnDelete();
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['section_id', 'name']);
        });

        Schema::create('ojt_evaluation_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ojt_evaluation_template_id')
                ->constrained('ojt_evaluation_templates')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('item_type');
            $table->string('label');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });

        if (! Schema::hasColumn('ojt_evaluations', 'evaluation_template_id')) {
            Schema::table('ojt_evaluations', function (Blueprint $table) {
                $table->foreignId('evaluation_template_id')
                    ->nullable()
                    ->after('opened_by_user_id')
                    ->constrained('ojt_evaluation_templates')
                    ->nullOnDelete();
                $table->json('responses')->nullable()->after('comments');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ojt_evaluations', 'evaluation_template_id')) {
            Schema::table('ojt_evaluations', function (Blueprint $table) {
                $table->dropConstrainedForeignId('evaluation_template_id');
                $table->dropColumn('responses');
            });
        }

        Schema::dropIfExists('ojt_evaluation_template_items');
        Schema::dropIfExists('ojt_evaluation_templates');
    }
};
