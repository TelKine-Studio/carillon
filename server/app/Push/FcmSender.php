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

/** Firebase Cloud Messaging HTTP v1 with a service account. */
class FcmSender implements Sender
{
    public const CONCURRENCY = 50;

    public function send(PushCredential $credential, Message $message, Collection $subscriptions): array
    {
        $account = json_decode((string) $credential->secret, true) ?: [];
        $url = 'https://fcm.googleapis.com/v1/projects/'.$account['project_id'].'/messages:send';
        $token = $this->accessToken($credential, $account);

        $responses = Http::pool(function (Pool $pool) use ($subscriptions, $message, $url, $token) {
            foreach ($subscriptions as $subscription) {
                $pool->as($subscription->id)
                    ->withOptions(['version' => 2.0])
                    ->withToken($token)
                    ->acceptJson()
                    ->timeout(15)
                    ->post($url, $subscription->usesOneSignalSdk()
                        ? Payload::oneSignalFcm($message, $subscription->subscriber?->language, (string) $subscription->token)
                        : Payload::fcm($message, $subscription->subscriber?->language, (string) $subscription->token));
            }
        }, self::CONCURRENCY);

        $results = [];
        foreach ($responses as $id => $response) {
            $results[$id] = $this->result($credential, $response);
        }

        return $results;
    }

    /**
     * Sends prepared messages (Live Activities): each item is the body of
     * messages:send.
     *
     * @param  array<string, array<string, mixed>>  $messages
     * @return array<string, Result>
     */
    public function push(PushCredential $credential, array $messages): array
    {
        $account = json_decode((string) $credential->secret, true) ?: [];
        $url = 'https://fcm.googleapis.com/v1/projects/'.$account['project_id'].'/messages:send';
        $token = $this->accessToken($credential, $account);

        $responses = Http::pool(function (Pool $pool) use ($messages, $url, $token) {
            foreach ($messages as $id => $message) {
                $pool->as((string) $id)->withOptions(['version' => 2.0])->withToken($token)->acceptJson()->timeout(15)->post($url, $message);
            }
        }, self::CONCURRENCY);

        $results = [];
        foreach ($responses as $id => $response) {
            $results[$id] = $this->result($credential, $response);
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

        $code = (string) ($response->json('error.details.0.errorCode') ?? $response->json('error.status') ?? 'HTTP '.$response->status());

        if ($response->status() === 401) {
            Cache::forget($this->cacheKey($credential));
        }

        // Uninstalled app, expired or malformed token, token of another project.
        if ($code === 'UNREGISTERED' || $code === 'SENDER_ID_MISMATCH'
            || ($code === 'INVALID_ARGUMENT' && str_contains((string) $response->json('error.message'), 'registration token'))) {
            return Result::invalid($code);
        }

        return in_array($response->status(), [401, 403, 429], true) || $response->status() >= 500
            ? Result::errored($code)
            : Result::failed($code);
    }

    /** OAuth token obtained with the service account (JWT RS256), valid one hour. */
    public function accessToken(PushCredential $credential, array $account): string
    {
        return Cache::remember($this->cacheKey($credential), now()->addMinutes(50), function () use ($account) {
            $now = time();
            $assertion = Jwt::sign(
                ['alg' => 'RS256', 'typ' => 'JWT'],
                [
                    'iss' => $account['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                    'aud' => $account['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ],
                $account['private_key'],
                ecdsa: false,
            );

            $response = Http::asForm()->timeout(15)->post($account['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Firebase refused the service account: '.($response->json('error_description') ?? $response->status()));
            }

            return $response->json('access_token');
        });
    }

    protected function cacheKey(PushCredential $credential): string
    {
        return 'fcm.token.'.$credential->id.'.'.sha1((string) $credential->secret);
    }
}
