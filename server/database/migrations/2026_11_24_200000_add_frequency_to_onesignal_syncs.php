<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How often the subscribers are fetched: every 1, 3, 6 or 12 hours, or
        // once a day at a chosen hour (UTC). Until now: every night at 3:30.
        Schema::table('onesignal_syncs', function (Blueprint $table) {
            $table->unsignedTinyInteger('every_hours')->default(24)->after('enabled');
            $table->unsignedTinyInteger('at_hour')->default(3)->after('every_hours');
        });
    }

    public function down(): void
    {
        Schema::table('onesignal_syncs', function (Blueprint $table) {
            $table->dropColumn(['every_hours', 'at_hour']);
        });
    }
};
