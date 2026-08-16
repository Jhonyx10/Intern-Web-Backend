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
        Schema::table('supervisors', function (Blueprint $table) {
            $table->timestamp('evaluation_pending_alerts_seen_at')
                ->nullable()
                ->after('is_active');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->timestamp('evaluation_completed_alerts_seen_at')
                ->nullable()
                ->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supervisors', function (Blueprint $table) {
            $table->dropColumn('evaluation_pending_alerts_seen_at');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('evaluation_completed_alerts_seen_at');
        });
    }
};
