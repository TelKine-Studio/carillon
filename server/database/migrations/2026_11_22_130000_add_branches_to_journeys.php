<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journeys', function (Blueprint $table) {
            // Hours of the day messages may go out ({from, to}, local time of the person).
            $table->json('send_window')->nullable()->after('reentry');
        });
        Schema::table('journey_entries', function (Blueprint $table) {
            // Start of a "wait for an event" step.
            $table->timestamp('waiting_since')->nullable()->after('next_run_at');
        });
    }

    public function down(): void
    {
        Schema::table('journeys', fn (Blueprint $table) => $table->dropColumn('send_window'));
        Schema::table('journey_entries', fn (Blueprint $table) => $table->dropColumn('waiting_since'));
    }
};
