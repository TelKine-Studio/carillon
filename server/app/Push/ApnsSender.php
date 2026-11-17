<?php

namespace App\Push;

use App\Models\Message;
use App\Models\PushCredential;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Apple Push Notification service, HTTP/2 with a .p8 key (token authentication). */
class ApnsSender implements Sender
{
    public const HOSTS = [
        'production' => 'https://api.push.apple.com',
        'sandbox' => 'https://api.sandbox.push.apple.com',
    ];

    /** Requests in flight at the same time (multiplexed on HTTP/2). */
    public const CONCURRENCY = 50;

    /** Reasons meaning the token will never work again. */
    protected const INVALID = ['BadDeviceToken', 'Unregistered', 'DeviceTokenNotForTopic', 'ExpiredToken'];

    public function send(PushCredential $credential, Message $message, Collection $subscriptions): array
    {
        $bundleId = (string) $credential->detail('bundle_id');
        $token = $this->token($credential);
        $payloads = [];

        $responses = Http::pool(function (Pool $pool) use ($subscriptions, $message, $bundleId, $token, &$payloads) {
            foreach ($subscriptions as $subscription) {
                $language = $subscription->subscriber?->language;
                $legacy = $subscription->usesOneSignalSdk();
                $payloads[$language][$legacy] ??= $legacy
                    ? Payload::oneSignalApns($message, $language, $bundleId)
                    : Payload::apns($message, $language, $bundleId);
                [$body, $headers] = $payloads[$language][$legacy];

                $pool->as($subscription->id)
                    ->withOptions(['version' => 2.0])
                    ->timeout(15)
                    ->withHeaders(['authorization' => 'bearer '.$token, ...$headers])
                    ->withBody(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json')
                    ->post((self::HOSTS[$subscription->environment] ?? self::HOSTS['production']).'/3/device/'.$subscription->token);
            }
        }, self::CONCURRENCY);

        $results = [];
        foreach ($responses as $id => $response) {
            $results[$id] = $this->result($credential, $response);
        }

        return $results;
    }

    /**
     * Sends prepared requests (Live Activities): each item has the token,
     * the environment, the JSON body and the headers.
     *
     * @param  array<string, array{token: string, environment: ?string, body: array<string, mixed>, headers: array<string, string>}>  $requests
     * @return array<string, Result>
     */
    public function push(PushCredential $credential, array $requests): array
    {
        $token = $this->token($credential);

        $responses = Http::pool(function (Pool $pool) use ($requests, $token) {
            foreach ($requests as $id => $request) {
                $pool->as((string) $id)
                    ->withOptions(['version' => 2.0])
                    ->timeout(15)
                    ->withHeaders(['authorization' => 'bearer '.$token, ...$request['headers']])
                    ->withBody(json_encode($request['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json')
                    ->post((self::HOSTS[$request['environment'] ?? ''] ?? self::HOSTS['production']).'/3/device/'.$request['token']);
            }
        }, self::CONCURRENCY);

        $results = [];
        foreach ($responses as $id => $response) {
            $request = $requests[$id] ?? $requests[(int) $id] ?? [];
            $results[$id] = $this->result($credential, $response)->withResponse([
                'status' => $response instanceof Response ? $response->status() : null,
                // apns-unique-id (development only) finds the push in the Push Notifications Console of Apple.
                'apns_id' => $response instanceof Response ? $response->header('apns-id') : null,
                'apns_unique_id' => $response instanceof Response ? $response->header('apns-unique-id') : null,
                'environment' => isset(self::HOSTS[$request['environment'] ?? '']) ? $request['environment'] : 'production',
                'token' => isset($request['token']) ? substr((string) $request['token'], 0, 8).'…'.substr((string) $request['token'], -6) : null,
            ]);
        }

        return $results;
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

        if (in_array($reason, ['ExpiredProviderToken', 'InvalidProviderToken'], true)) {
            Cache::forget($this->cacheKey($credential));
        }

        if ($response->status() === 410 || in_array($reason, self::INVALID, true)) {
            return Result::invalid($reason);
        }

        // Key, topic or Apple-side problems concern every device: errored.
        return $response->status() >= 500 || $response->status() === 429 || $response->status() === 403
            ? Result::errored($reason)
            : Result::failed($reason);
    }

    /** Provider token, valid one hour; Apple refuses refreshing it more than every 20 minutes. */
    public function token(PushCredential $credential): string
    {
        return Cache::remember($this->cacheKey($credential), now()->addMinutes(45), fn () => Jwt::sign(
            ['alg' => 'ES256', 'kid' => $credential->detail('key_id')],
            ['iss' => $credential->detail('team_id'), 'iat' => time()],
            (string) $credential->secret,
            ecdsa: true,
        ));
    }

    protected function cacheKey(PushCredential $credential): string
    {
        return 'apns.jwt.'.$credential->id.'.'.sha1($credential->detail('key_id').$credential->detail('team_id').$credential->secret);
    }
}
