<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Activity per hour of the day (UTC) of each person, for the smart send time. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->json('hour_counts')->nullable()->after('first_seen_at');
        });

        // What is known: the last activity, and the clicks of the last 90 days.
        $counts = [];
        DB::table('subscribers')->whereNotNull('last_active_at')->select(['id', 'last_active_at'])->orderBy('id')
            ->chunk(2000, function ($rows) use (&$counts) {
                foreach ($rows as $row) {
                    $hour = (int) substr((string) $row->last_active_at, 11, 2);
                    $counts[$row->id][$hour] = ($counts[$row->id][$hour] ?? 0) + 1;
                }
            });
        DB::table('deliveries')->join('subscriptions', 'subscriptions.id', '=', 'deliveries.subscription_id')
            ->whereNotNull('deliveries.clicked_at')->where('deliveries.clicked_at', '>=', now()->subDays(90))
            ->select(['deliveries.id', 'subscriptions.subscriber_id', 'deliveries.clicked_at'])->orderBy('deliveries.id')
            ->chunk(2000, function ($rows) use (&$counts) {
                foreach ($rows as $row) {
                    $hour = (int) substr((string) $row->clicked_at, 11, 2);
                    $counts[$row->subscriber_id][$hour] = ($counts[$row->subscriber_id][$hour] ?? 0) + 2;
                }
            });
        foreach ($counts as $id => $hours) {
            DB::table('subscribers')->where('id', $id)->update(['hour_counts' => json_encode($hours)]);
        }
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('hour_counts');
        });
    }
};
