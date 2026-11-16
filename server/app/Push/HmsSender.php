<?php

namespace App\Push;

use App\Models\Message;
use App\Models\PushCredential;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Huawei Push Kit (HMS) for Huawei phones without Google services:
 * details {app_id}, secret = app secret (AppGallery Connect). Notification
 * messages, shown by the system; up to 1000 tokens per request.
 */
class HmsSender implements Sender
{
    public const TOKENS_PER_REQUEST = 1000;

    public function send(PushCredential $credential, Message $message, Collection $subscriptions): array
    {
        $appId = (string) $credential->detail('app_id');
        $token = $this->accessToken($credential);
        $results = [];

        // One request per language (the texts differ) and per 1000 devices.
        foreach ($subscriptions->groupBy(fn ($s) => (string) $s->subscriber?->language) as $language => $group) {
            foreach ($group->chunk(self::TOKENS_PER_REQUEST) as $chunk) {
                try {
                    $response = Http::withToken($token)->acceptJson()->timeout(20)
                        ->post("https://push-api.cloud.huawei.com/v1/{$appId}/messages:send", self::payload($message, $language ?: null, $chunk->pluck('token')->all()));
                } catch (Throwable $e) {
                    foreach ($chunk as $subscription) {
                        $results[$subscription->id] = Result::errored('Network: '.mb_substr($e->getMessage(), 0, 150));
                    }

                    continue;
                }
                $results += $this->results($credential, $response, $chunk);
            }
        }

        return $results;
    }

    /** @param list<string> $tokens */
    public static function payload(Message $message, ?string $language, array $tokens): array
    {
        $common = Payload::common($message, $language);
        $url = $common['u'] ?? null;
        $ttl = $message->option('ttl');

        return [
            'validate_only' => false,
            'message' => [
                'data' => json_encode(['carillon' => $common], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'android' => array_filter([
                    'urgency' => (int) $message->option('priority', 10) >= 10 ? 'HIGH' : 'NORMAL',
                    'ttl' => $ttl !== null ? ((int) $ttl).'s' : null,
                    'notification' => array_filter([
                        'title' => $common['title'] ?? null,
                        'body' => $common['body'] ?? '',
                        'image' => $common['img'] ?? null,
                        'channel_id' => $message->option('android_channel_id'),
                        // 2: open the link, 3: open the app.
                        'click_action' => $url && preg_match('#^https?://#i', $url) ? ['type' => 2, 'url' => $url] : ['type' => 3],
                    ], fn ($value) => $value !== null),
                ], fn ($value) => $value !== null),
                'token' => array_values($tokens),
            ],
        ];
    }

    /** @return array<string, Result> */
    protected function results(PushCredential $credential, Response $response, Collection $chunk): array
    {
        $code = (string) $response->json('code');
        $results = [];

        if ($response->status() === 401 || in_array($code, ['80200001', '80200003'], true)) {
            Cache::forget($this->cacheKey($credential));
        }

        // 80000000: all sent; 80100000: some tokens refused, listed in "msg".
        if ($code === '80000000' || $code === '80100000') {
            $detail = json_decode((string) $response->json('msg'), true);
            $illegal = array_flip((array) ($detail['illegal_tokens'] ?? []));
            foreach ($chunk as $subscription) {
                $results[$subscription->id] = isset($illegal[$subscription->token]) ? Result::invalid('Illegal token') : Result::sent();
            }

            return $results;
        }

        $reason = mb_substr(trim($code.' '.$response->json('msg', $response->reason())), 0, 180);
        foreach ($chunk as $subscription) {
            // 80300007: every token of the request is invalid.
            $results[$subscription->id] = $code === '80300007'
                ? Result::invalid($reason)
                : ($response->status() >= 500 || in_array($response->status(), [401, 403, 429], true) ? Result::errored($reason) : Result::failed($reason));
        }

        return $results;
    }

    public function accessToken(PushCredential $credential): string
    {
        return Cache::remember($this->cacheKey($credential), now()->addMinutes(50), function () use ($credential) {
            $response = Http::asForm()->acceptJson()->timeout(15)->post('https://oauth-login.cloud.huawei.com/oauth2/v3/token', [
                'grant_type' => 'client_credentials',
                'client_id' => (string) $credential->detail('app_id'),
                'client_secret' => (string) $credential->secret,
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Huawei refused the app id or secret: '.($response->json('error_description') ?? $response->json('error') ?? $response->status()));
            }

            return (string) $response->json('access_token');
        });
    }

    protected function cacheKey(PushCredential $credential): string
    {
        return 'hms.token.'.$credential->id.'.'.sha1((string) $credential->secret);
    }
}
