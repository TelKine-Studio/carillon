<?php

namespace App\Audience;

use App\Models\Delivery;
use App\Models\Subscription;
use App\Webhooks\Webhooks;
use Illuminate\Support\Facades\DB;

/**
 * Reception and click of a message by a device (reported by the SDKs, or
 * by the email open pixel and tracked links). Counted once per device.
 */
class Events
{
    /** @return bool whether it was new */
    public static function record(Subscription $subscription, string $messageId, string $event, ?string $actionId = null): bool
    {
        $column = $event.'_at';
        $now = now();
        $values = [$column => $now];
        // A click proves the reception (emails whose images are blocked).
        if ($event === 'clicked') {
            self::record($subscription, $messageId, 'received');
        }

        $updated = Delivery::query()
            ->where('message_id', $messageId)
            ->where('subscription_id', $subscription->id)
            ->whereNull($column)
            ->update($values);

        if (! $updated) {
            return false;
        }

        DB::table('messages')->where('id', $messageId)->increment($event);
        if ($event === 'clicked' && $subscription->subscriber_id) {
            Activity::click($subscription->subscriber_id, $now);
        }
        DB::table('message_platforms')->where('message_id', $messageId)->where('platform', $subscription->type)->increment($event);

        $type = $subscription->type === 'email' ? 'email.'.($event === 'received' ? 'opened' : 'clicked') : 'notification.'.$event;
        Webhooks::dispatch($subscription->project_id, $type, array_filter([
            'message_id' => $messageId,
            'subscription_id' => $subscription->id,
            'external_id' => $subscription->subscriber?->external_id,
            'platform' => $subscription->type,
            'action_id' => $event === 'clicked' ? $actionId : null,
        ], fn ($value) => $value !== null));

        return true;
    }
}
