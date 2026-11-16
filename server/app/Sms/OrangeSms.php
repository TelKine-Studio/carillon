<?php

namespace App\Sms;

use App\Models\Message;
use App\Models\PushCredential;
use App\Push\Result;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Orange SMS API (GSMA OneAPI): an OAuth token from the client id and secret
 * (2-legged, valid about an hour, kept in cache), then one request per number.
 */
class OrangeSms
{
    public const BASE = 'https://api.orange.com';

    protected const CONCURRENCY = 5;

    /** Errors meaning the number cannot receive SMS (invalid address). */
    protected const DEAD = ['SVC0004'];

    /** @return array<string, Result> */
    public function send(PushCredential $credential, Message $message, Collection $subscriptions): array
    {
        $token = $this->token($credential);
        $sender = 'tel:'.$credential->detail('sender_address');
        $url = self::BASE.'/smsmessaging/v1/outbound/'.rawurlencode($sender).'/requests';
        $results = [];

        foreach ($subscriptions->chunk(self::CONCURRENCY) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => $chunk->map(fn ($subscription) => $pool->as($subscription->id)
                ->withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->post($url, ['outboundSMSMessageRequest' => array_filter([
                    'address' => 'tel:'.$subscription->token,
                    'senderAddress' => $sender,
                    'senderName' => $message->option('sms_from') ?: $credential->detail('sender_name'),
                    'outboundSMSTextMessage' => ['message' => SmsSender::body($message, $subscription)],
                ])]))->all());

            foreach ($chunk as $subscription) {
                $results[$subscription->id] = $this->result($responses[$subscription->id] ?? null, $credential);
            }
        }

        return $results;
    }

    /** Access token for the client id, renewed a minute before it expires. */
    public function token(PushCredential $credential): string
    {
        return Cache::remember($this->cacheKey($credential), now()->addMinutes(50), function () use ($credential) {
            $response = Http::asForm()
                ->withBasicAuth((string) $credential->detail('client_id'), (string) $credential->secret)
                ->acceptJson()
                ->timeout(20)
                ->post(self::BASE.'/oauth/v3/token', ['grant_type' => 'client_credentials']);

            if (! $response->successful() || ! $response->json('access_token')) {
                throw new RuntimeException('Orange refused the client id or secret: '.mb_substr((string) ($response->json('error_description') ?? $response->json('error') ?? $response->status()), 0, 150));
            }

            return (string) $response->json('access_token');
        });
    }

    /** Changes with the credentials: new keys, new token. */
    protected function cacheKey(PushCredential $credential): string
    {
        return 'orange-sms-token:'.$credential->id.':'.md5((string) $credential->detail('client_id').$credential->updated_at);
    }

    protected function result(mixed $response, PushCredential $credential): Result
    {
        if ($response instanceof Throwable || ! $response instanceof Response) {
            return Result::errored($response instanceof Throwable ? mb_substr($response->getMessage(), 0, 180) : 'No response from Orange.');
        }
        if ($response->successful()) {
            return Result::sent();
        }

        $exception = $response->json('requestError.serviceException') ?? $response->json('requestError.policyException') ?? [];
        $code = (string) ($exception['messageId'] ?? $response->json('code') ?? '');
        $text = (string) ($exception['text'] ?? $response->json('message') ?? $response->reason());
        $reason = mb_substr(trim($code.' '.str_replace('%1', (string) ($exception['variables'] ?? ''), $text)), 0, 180);

        if ($response->status() === 401) {
            // Expired or revoked token: the next batch asks for a new one.
            Cache::forget($this->cacheKey($credential));
        }
        if (in_array($code, self::DEAD, true)) {
            return Result::invalid($reason);
        }

        return in_array($response->status(), [400, 403], true) ? Result::failed($reason) : Result::errored($reason);
    }
}
