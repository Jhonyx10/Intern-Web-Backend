<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_student', function (Blueprint $table) {
            $table->text('removal_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('company_student', function (Blueprint $table) {
            $table->dropColumn('removal_reason');
        });
    }
};
