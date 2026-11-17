<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Like OneSignal: "Never subscribed" (no subscribed_at), "Last
        // unsubscribed", and the time spent in the app (heartbeats).
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->unsignedInteger('usage_seconds')->default(0);
        });

        // Known devices: subscribed since their registration when they still
        // are, or when they received a notification.
        DB::table('subscriptions')->where('enabled', true)->update(['subscribed_at' => DB::raw('created_at')]);
        DB::table('subscriptions')->whereNull('subscribed_at')->select('id')->chunkById(500, function ($rows) {
            $first = DB::table('deliveries')->whereIn('subscription_id', $rows->pluck('id'))->where('status', 'sent')
                ->groupBy('subscription_id')->selectRaw('subscription_id, min(sent_at) as first_sent')->pluck('first_sent', 'subscription_id');
            foreach ($first as $id => $at) {
                DB::table('subscriptions')->where('id', $id)->update(['subscribed_at' => $at]);
            }
        });
        DB::table('subscriptions')->whereNotNull('invalid_at')->update(['unsubscribed_at' => DB::raw('coalesce(status_changed_at, invalid_at)')]);
        DB::table('subscriptions')->where('enabled', false)->whereNull('unsubscribed_at')->whereNotNull('subscribed_at')
            ->update(['unsubscribed_at' => DB::raw('coalesce(status_changed_at, updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['subscribed_at', 'unsubscribed_at', 'usage_seconds']);
        });
    }
};
