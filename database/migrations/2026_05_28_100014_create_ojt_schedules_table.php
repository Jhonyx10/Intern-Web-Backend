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
        Schema::create('ojt_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                ->unique()
                ->constrained('students')
                ->cascadeOnDelete();
            $table->decimal('hours_per_day', 4, 2)->default(8);
            $table->unsignedTinyInteger('days_per_week')->default(5);
            $table->date('start_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ojt_schedules');
    }
};
