<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('supervisors')->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'student_id']);
        });

        if (Schema::hasColumn('students', 'company_id')) {
            $placements = DB::table('students')
                ->whereNotNull('company_id')
                ->select('id as student_id', 'company_id')
                ->get();

            $now = now();

            foreach ($placements as $placement) {
                DB::table('company_student')->insert([
                    'company_id' => $placement->company_id,
                    'student_id' => $placement->student_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Schema::table('students', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'company_id')) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('section_id')
                    ->constrained('companies')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasTable('company_student')) {
            $placements = DB::table('company_student')
                ->select('student_id', DB::raw('MIN(company_id) as company_id'))
                ->groupBy('student_id')
                ->get();

            foreach ($placements as $placement) {
                DB::table('students')
                    ->where('id', $placement->student_id)
                    ->update(['company_id' => $placement->company_id]);
            }
        }

        Schema::dropIfExists('company_student');
    }
};
