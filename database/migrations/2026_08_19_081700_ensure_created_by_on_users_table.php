<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original created_by migration may already have run as an empty stub.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'created_by')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('course_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        $coordinatorRoleId = Schema::hasTable('roles')
            ? DB::table('roles')->where('name', 'coordinator')->value('id')
            : null;

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

    public function down(): void
    {
        // Column ownership stays with the original created_by migration.
    }
};
