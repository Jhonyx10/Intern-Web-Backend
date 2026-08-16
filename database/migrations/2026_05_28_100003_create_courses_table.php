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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('required_hours')->default(0);
            $table->foreignId('dean_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('dean_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
