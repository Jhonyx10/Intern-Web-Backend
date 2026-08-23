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
        Schema::create('company_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
            $table->date('start_date');
            $table->time('time_in');
            $table->string('lunch_break')->nullable(); // e.g. '12:00-13:00' or '60 mins'
            $table->time('time_out');
            $table->foreignId('supervisor_id')
                ->nullable()
                ->constrained('supervisors')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_schedules');
    }
};
