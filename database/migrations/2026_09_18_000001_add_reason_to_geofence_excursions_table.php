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
        Schema::table('geofence_excursions', function (Blueprint $table) {
            if (!Schema::hasColumn('geofence_excursions', 'reason')) {
                $table->text('reason')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('geofence_excursions', function (Blueprint $table) {
            if (Schema::hasColumn('geofence_excursions', 'reason')) {
                $table->dropColumn('reason');
            }
        });
    }
};
