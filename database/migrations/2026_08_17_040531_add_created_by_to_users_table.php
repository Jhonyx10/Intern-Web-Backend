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
        $this->ensureCreatedByColumn();
        $this->backfillCoordinatorCreators();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'created_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
            });
        }
    }

    private function ensureCreatedByColumn(): void
    {
        if (Schema::hasColumn('users', 'created_by')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('created_by')
                ->nullable()
                ->after('course_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    private function backfillCoordinatorCreators(): void
    {
        if (! Schema::hasColumn('users', 'created_by') || ! Schema::hasTable('roles')) {
            return;
        }

        $coordinatorRoleId = DB::table('roles')->where('name', 'coordinator')->value('id');

        if ($coordinatorRoleId === null) {
            return;
        }

        DB::table('users')
            ->where('role_id', $coordinatorRoleId)
            ->whereNull('created_by')
            ->whereNotNull('course_id')
            ->orderBy('id')
            ->each(function (object $coordinator): void {
                $deanId = DB::table('courses')
                    ->where('id', $coordinator->course_id)
                    ->value('dean_user_id');

                if ($deanId === null) {
                    return;
                }

                DB::table('users')->where('id', $coordinator->id)->update([
                    'created_by' => $deanId,
                ]);
            });
    }
};
