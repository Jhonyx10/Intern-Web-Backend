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
        if (! Schema::hasColumn('course_majors', 'program_head_user_id')) {
            Schema::table('course_majors', function (Blueprint $table) {
                $table->foreignId('program_head_user_id')
                    ->nullable()
                    ->after('code')
                    ->constrained('users')
                    ->nullOnDelete();

                $table->unique('program_head_user_id');
            });
        }

        if (Schema::hasColumn('course_majors', 'program_head_name')) {
            Schema::table('course_majors', function (Blueprint $table) {
                $table->dropColumn('program_head_name');
            });
        }

        if (! Schema::hasColumn('sections', 'course_major_id')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->foreignId('course_major_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('course_majors')
                    ->nullOnDelete();
            });
        }

        if ($this->hasLegacySectionUniqueIndex()) {
            Schema::table('sections', function (Blueprint $table) {
                $table->index('course_id', 'sections_course_id_index');
            });

            Schema::table('sections', function (Blueprint $table) {
                $table->dropUnique(['course_id', 'school_year_id', 'name']);
                $table->unique(
                    ['course_id', 'school_year_id', 'course_major_id', 'name'],
                    'sections_course_school_year_major_name_unique',
                );
            });
        }

        if (! Schema::hasColumn('supervisors', 'is_department_head')) {
            Schema::table('supervisors', function (Blueprint $table) {
                $table->boolean('is_department_head')
                    ->default(false)
                    ->after('is_active');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->hasMajorScopedSectionUniqueIndex()) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropUnique('sections_course_school_year_major_name_unique');
            });

            Schema::table('sections', function (Blueprint $table) {
                $table->unique(['course_id', 'school_year_id', 'name']);
                $table->dropIndex('sections_course_id_index');
            });
        }

        if (Schema::hasColumn('sections', 'course_major_id')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropConstrainedForeignId('course_major_id');
            });
        }

        if (! Schema::hasColumn('course_majors', 'program_head_name')) {
            Schema::table('course_majors', function (Blueprint $table) {
                $table->string('program_head_name')->nullable()->after('code');
            });
        }

        if (Schema::hasColumn('course_majors', 'program_head_user_id')) {
            Schema::table('course_majors', function (Blueprint $table) {
                $table->dropUnique(['program_head_user_id']);
                $table->dropConstrainedForeignId('program_head_user_id');
            });
        }

        if (Schema::hasColumn('supervisors', 'is_department_head')) {
            Schema::table('supervisors', function (Blueprint $table) {
                $table->dropColumn('is_department_head');
            });
        }
    }

    private function hasLegacySectionUniqueIndex(): bool
    {
        $indexes = Schema::getIndexes('sections');

        foreach ($indexes as $index) {
            if (($index['name'] ?? '') === 'sections_course_id_school_year_id_name_unique') {
                return true;
            }
        }

        return false;
    }

    private function hasMajorScopedSectionUniqueIndex(): bool
    {
        $indexes = Schema::getIndexes('sections');

        foreach ($indexes as $index) {
            if (($index['name'] ?? '') === 'sections_course_school_year_major_name_unique') {
                return true;
            }
        }

        return false;
    }
};
