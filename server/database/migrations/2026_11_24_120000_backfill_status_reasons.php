<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Status details of the devices already unreachable: the last refusal of
     * the provider kept in the deliveries, or a token taken by another
     * registration (the only case where the token was emptied).
     */
    public function up(): void
    {
        DB::table('subscriptions')->whereNotNull('invalid_at')->whereNull('status_reason')->whereNull('token')
            ->update(['status_reason' => 'token_reused', 'status_changed_at' => DB::raw('invalid_at')]);

        DB::table('subscriptions')->whereNotNull('invalid_at')->whereNull('status_reason')
            ->select('id')->chunkById(500, function ($rows) {
                $errors = DB::table('deliveries')
                    ->whereIn('subscription_id', $rows->pluck('id'))
                    ->where('status', 'failed')
                    ->whereNotNull('error')
                    ->orderBy('sent_at')
                    ->get(['subscription_id', 'error', 'sent_at'])
                    ->keyBy('subscription_id');

                foreach ($errors as $id => $delivery) {
                    DB::table('subscriptions')->where('id', $id)->update([
                        'status_reason' => 'rejected',
                        'status_detail' => mb_substr((string) $delivery->error, 0, 255),
                        'status_changed_at' => $delivery->sent_at,
                    ]);
                }
            });
    }

    public function down(): void {}
};
