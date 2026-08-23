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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('department_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('theme_color')->default('#0b6e4f');
            $table->string('theme_color_hover')->nullable();
            $table->string('theme_color_soft')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
