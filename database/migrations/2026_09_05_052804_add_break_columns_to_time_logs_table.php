<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('time_logs', function (Blueprint $table) {
            $table->timestamp('break_out')->nullable()->after('time_in');
            $table->timestamp('break_in')->nullable()->after('break_out');
        });
    }

    public function down()
    {
        Schema::table('time_logs', function (Blueprint $table) {
            $table->dropColumn(['break_out', 'break_in']);
        });
    }

};
