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
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            
            // Parent Company Foreign Key
            $table->foreignId('company_id')
                  ->constrained('companies')
                  ->cascadeOnDelete();

            $table->string('name');
            $table->string('code')->nullable(); // Optional building code e.g., BLDG-A
            $table->text('description')->nullable();

            // Point Location Coordinates
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Dynamic Geofence Settings
            $table->integer('geofence_radius_meters')->default(30);
            $table->boolean('geofence_enabled')->default(true);
            
            // Stores GeoJSON format: {"type": "Polygon", "coordinates": [[[lng, lat], ...]]}
            $table->json('geofence_polygon')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};