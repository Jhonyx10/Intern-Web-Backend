<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('program_head_id')
                  ->nullable()
                  ->after('dean_user_id')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['program_head_id']);
            $table->dropColumn('program_head_id');
        });
    }
};
