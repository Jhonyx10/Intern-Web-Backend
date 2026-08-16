<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE companies MODIFY geofence_radius_meters INT UNSIGNED NOT NULL DEFAULT 10',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'ALTER TABLE companies MODIFY geofence_radius_meters INT UNSIGNED NOT NULL DEFAULT 1000',
        );
    }
};
