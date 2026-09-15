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
        Schema::create('geofence_excursion_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('excursion_id')->constrained('geofence_excursions')->cascadeOnDelete();
            $table->float('latitude', 10, 6);
            $table->float('longitude', 10, 6);
            $table->float('distance_from_boundary_meters')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofence_excursion_points');
    }
};
