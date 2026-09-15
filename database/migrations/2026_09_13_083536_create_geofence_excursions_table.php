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
        Schema::create('geofence_excursions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('exit_at');
            $table->timestamp('return_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->float('max_distance_meters')->nullable();
            $table->enum('status', ['ongoing', 'completed'])->default('ongoing');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofence_excursions');
    }
};
