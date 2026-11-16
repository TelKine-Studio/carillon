<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ends sent for an activity: Apple may accept an end that never reaches the
 * device, so it is sent again until the app confirms (exit) or 3 tries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_activities', function (Blueprint $table) {
            $table->unsignedTinyInteger('ends')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('live_activities', function (Blueprint $table) {
            $table->dropColumn('ends');
        });
    }
};
