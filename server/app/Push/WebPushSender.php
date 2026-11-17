<?php

namespace App\Push;

use App\Models\Message;
use App\Models\PushCredential;
use Illuminate\Support\Collection;
use Minishlink\WebPush\Subscription as WebSubscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/** Web Push API with VAPID keys (Chrome, Edge, Firefox, Safari). */
class WebPushSender implements Sender
{
    public function send(PushCredential $credential, Message $message, Collection $subscriptions): array
    {
        $results = [];

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => $credential->detail('subject') ?: 'mailto:push@localhost',
                'publicKey' => $credential->detail('public_key'),
                'privateKey' => $credential->secret,
            ]], ['TTL' => (int) $message->option('ttl', 259200), 'urgency' => (int) $message->option('priority', 10) >= 10 ? 'high' : 'normal']);
            $webPush->setReuseVAPIDHeaders(true);
        } catch (Throwable $e) {
            return $subscriptions->mapWithKeys(fn ($s) => [$s->id => Result::errored('VAPID: '.$e->getMessage())])->all();
        }

        $byEndpoint = [];
        foreach ($subscriptions as $subscription) {
            $data = $subscription->webSubscription();

            if (! isset($data['endpoint'])) {
                $results[$subscription->id] = Result::invalid('Malformed subscription');

                continue;
            }
            if (! WebPushEndpoint::allowed($data['endpoint'])) {
                $results[$subscription->id] = Result::invalid('Endpoint not allowed');

                continue;
            }

            $byEndpoint[$data['endpoint']] = $subscription->id;
            $webPush->queueNotification(
                WebSubscription::create($data),
                json_encode(Payload::web($message, $subscription->subscriber?->language), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
        }

        foreach ($webPush->flush(200) as $report) {
            $id = $byEndpoint[$report->getEndpoint()] ?? null;
            if (! $id) {
                continue;
            }

            $results[$id] = match (true) {
                $report->isSuccess() => Result::sent(),
                $report->isSubscriptionExpired() => Result::invalid('Expired subscription'),
                default => Result::errored(mb_substr($report->getReason(), 0, 180)),
            };
        }

        return $results;
    }
}
