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
        Schema::table('ojt_schedules', function (Blueprint $table) {
            // Drop the old foreign key & unique index on student_id
            // Laravel auto-names them as: ojt_schedules_student_id_foreign, ojt_schedules_student_id_unique
            if (Schema::hasColumn('ojt_schedules', 'student_id')) {
                $table->dropForeign(['student_id']);
                $table->dropUnique(['student_id']);
                $table->dropColumn('student_id');
            }
        });

        Schema::table('ojt_schedules', function (Blueprint $table) {
            // company_student_id FK
            if (!Schema::hasColumn('ojt_schedules', 'company_student_id')) {
                $table->unsignedBigInteger('company_student_id')->after('id');
                $table->foreign('company_student_id')
                    ->references('id')
                    ->on('company_student')
                    ->cascadeOnDelete();
            }

            // Requested time-in / time-out
            if (!Schema::hasColumn('ojt_schedules', 'time_in')) {
                $table->time('time_in')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('ojt_schedules', 'time_out')) {
                $table->time('time_out')->nullable()->after('time_in');
            }

            // Approval workflow
            if (!Schema::hasColumn('ojt_schedules', 'status')) {
                $table->enum('status', ['pending', 'approved', 'rejected'])
                    ->default('pending')
                    ->after('time_out');
            }
            if (!Schema::hasColumn('ojt_schedules', 'reason')) {
                $table->text('reason')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ojt_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('ojt_schedules', 'company_student_id')) {
                $table->dropForeign(['company_student_id']);
                $table->dropColumn('company_student_id');
            }
            foreach (['time_in', 'time_out', 'status', 'reason'] as $col) {
                if (Schema::hasColumn('ojt_schedules', $col)) {
                    $table->dropColumn($col);
                }
            }

            if (!Schema::hasColumn('ojt_schedules', 'student_id')) {
                $table->foreignId('student_id')
                    ->unique()
                    ->constrained('students')
                    ->cascadeOnDelete();
            }
        });
    }
};
