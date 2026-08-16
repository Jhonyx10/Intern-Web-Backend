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
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'section_id')) {
                $table->dropForeign(['section_id']);
            }

            if (Schema::hasColumn('companies', 'course_id')) {
                $table->dropForeign(['course_id']);
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if ($this->courseNameUniqueIndexExists()) {
                $table->dropUnique(['course_id', 'name']);
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('companies', 'section_id')) {
                $columns[] = 'section_id';
            }

            if (Schema::hasColumn('companies', 'course_id')) {
                $columns[] = 'course_id';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'company_request_id')) {
                $table->foreignId('company_request_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('company_requests')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'company_request_id')) {
                $table->dropConstrainedForeignId('company_request_id');
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'course_id')) {
                $table->foreignId('course_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('courses')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('companies', 'section_id')) {
                $table->foreignId('section_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('sections')
                    ->nullOnDelete();
            }
        });

        if (! $this->courseNameUniqueIndexExists()) {
            Schema::table('companies', function (Blueprint $table) {
                $table->unique(['course_id', 'name']);
            });
        }
    }

    private function courseNameUniqueIndexExists(): bool
    {
        return collect(Schema::getIndexes('companies'))
            ->contains(fn (array $index) => $index['unique'] === true
                && collect($index['columns'])->sort()->values()->all() === ['course_id', 'name']);
    }
};
