<?php

namespace App\Push;

use App\Models\Message;
use App\Models\PushCredential;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Amazon Device Messaging for Fire tablets and Fire TV: details {client_id},
 * secret = client secret. Data messages (6 KB), shown by the app.
 */
class AdmSender implements Sender
{
    public const CONCURRENCY = 20;

    public function send(PushCredential $credential, Message $message, Collection $subscriptions): array
    {
        $token = $this->accessToken($credential);

        $responses = Http::pool(function (Pool $pool) use ($subscriptions, $message, $token) {
            foreach ($subscriptions as $subscription) {
                $pool->as($subscription->id)
                    ->withToken($token)
                    ->withHeaders([
                        'X-Amzn-Type-Version' => 'com.amazon.device.messaging.ADMMessage@1.0',
                        'X-Amzn-Accept-Type' => 'com.amazon.device.messaging.ADMSendResult@1.0',
                    ])
                    ->acceptJson()
                    ->timeout(15)
                    ->post('https://api.amazon.com/messaging/registrations/'.rawurlencode((string) $subscription->token).'/messages', self::payload($message, $subscription->subscriber?->language, $subscription->usesOneSignalSdk()));
            }
        }, self::CONCURRENCY);

        $results = [];
        foreach ($responses as $id => $response) {
            $results[$id] = $this->result($credential, $response);
        }

        return $results;
    }

    /** Apps still on the OneSignal SDK read its own data keys (alert, title, custom…). */
    public static function payload(Message $message, ?string $language, bool $oneSignal = false): array
    {
        $ttl = $message->option('ttl');
        $data = $oneSignal
            ? Payload::oneSignalFcm($message, $language, '')['message']['data']
            : ['carillon' => json_encode(Payload::common($message, $language), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];

        return array_filter([
            'data' => $data,
            'priority' => (int) $message->option('priority', 10) >= 10 ? 'high' : 'normal',
            'consolidationKey' => $message->option('collapse_id') ? mb_substr((string) $message->option('collapse_id'), 0, 64) : null,
            // ADM keeps messages 60 s to 31 days.
            'expiresAfter' => $ttl !== null ? max(60, min(2678400, (int) $ttl)) : null,
        ], fn ($value) => $value !== null);
    }

    protected function result(PushCredential $credential, mixed $response): Result
    {
        if (! $response instanceof Response) {
            return Result::errored($response instanceof Throwable ? 'Network: '.$response->getMessage() : 'No response');
        }
        if ($response->successful()) {
            return Result::sent();
        }

        $reason = (string) ($response->json('reason') ?? 'HTTP '.$response->status());
        if ($response->status() === 401) {
            Cache::forget($this->cacheKey($credential));
        }
        if (in_array($reason, ['Unregistered', 'InvalidRegistrationId'], true)) {
            return Result::invalid($reason);
        }

        return $response->status() >= 500 || in_array($response->status(), [401, 429], true) ? Result::errored($reason) : Result::failed($reason);
    }

    public function accessToken(PushCredential $credential): string
    {
        return Cache::remember($this->cacheKey($credential), now()->addMinutes(50), function () use ($credential) {
            $response = Http::asForm()->acceptJson()->timeout(15)->post('https://api.amazon.com/auth/O2/token', [
                'grant_type' => 'client_credentials',
                'scope' => 'messaging:push',
                'client_id' => (string) $credential->detail('client_id'),
                'client_secret' => (string) $credential->secret,
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Amazon refused the client id or secret: '.($response->json('error_description') ?? $response->json('error') ?? $response->status()));
            }

            return (string) $response->json('access_token');
        });
    }

    protected function cacheKey(PushCredential $credential): string
    {
        return 'adm.token.'.$credential->id.'.'.sha1((string) $credential->secret);
    }
}
