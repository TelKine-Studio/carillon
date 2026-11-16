<?php

namespace App\Sms;

use App\Email\EmailBuilder;
use App\Models\Message;
use App\Models\PushCredential;
use App\Push\Result;
use App\Push\Sender;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends a message by SMS, with the provider of the project:
 * - twilio: details {account_sid, from}, secret = auth token; "from" is a
 *   number, an alphanumeric sender id or a Messaging Service id (MG…);
 * - orange: details {client_id, sender_address, sender_name?}, secret =
 *   client secret (Orange SMS API, developer.orange.com).
 */
class SmsSender implements Sender
{
    /** Requests sent together; Twilio queues them at the rate of the sender. */
    protected const CONCURRENCY = 10;

    /** Twilio errors that mean the number will never receive anything. */
    protected const DEAD = [21211, 21214, 21217, 21401, 21407, 21408, 21421, 21606, 21610, 21612, 21614, 30006];

    public function send(PushCredential $credential, Message $message, Collection $subscriptions): array
    {
        if ($credential->detail('driver') === 'orange') {
            return app(OrangeSms::class)->send($credential, $message, $subscriptions);
        }

        $sid = (string) $credential->detail('account_sid');
        $from = (string) ($message->option('sms_from') ?: $credential->detail('from'));
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        $media = array_values(array_filter((array) $message->option('sms_media_urls', [])));
        $results = [];

        foreach ($subscriptions->chunk(self::CONCURRENCY) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => $chunk->map(function ($subscription) use ($pool, $url, $sid, $credential, $from, $message, $media) {
                $form = [
                    'To' => $subscription->token,
                    'Body' => self::body($message, $subscription),
                    str_starts_with($from, 'MG') ? 'MessagingServiceSid' : 'From' => $from,
                ];
                foreach ($media as $index => $mediaUrl) {
                    $form['MediaUrl'.($index ?: '')] = $mediaUrl;
                }

                return $pool->as($subscription->id)->asForm()->withBasicAuth($sid, (string) $credential->secret)->timeout(20)->post($url, $form);
            })->all());

            foreach ($chunk as $subscription) {
                $results[$subscription->id] = self::result($responses[$subscription->id] ?? null);
            }
        }

        return $results;
    }

    /** The text in the language of the person, with their tags filled in. */
    public static function body(Message $message, $subscription): string
    {
        $text = (string) Message::localized($message->contents, $subscription->subscriber?->language);

        return EmailBuilder::render($text, [
            'phone_number' => $subscription->token,
            'external_id' => $subscription->subscriber?->external_id,
            'tags' => $subscription->subscriber?->tags ?? [],
        ], false);
    }

    protected static function result(mixed $response): Result
    {
        if ($response instanceof Throwable || ! $response instanceof Response) {
            return Result::errored($response instanceof Throwable ? mb_substr($response->getMessage(), 0, 180) : 'No response from Twilio.');
        }
        if ($response->successful()) {
            return Result::sent();
        }

        $code = (int) $response->json('code');
        $reason = mb_substr(trim(($code ? $code.' ' : '').$response->json('message', $response->reason())), 0, 180);

        if (in_array($code, self::DEAD, true)) {
            return Result::invalid($reason);
        }

        // Refused for this message (content, region…), or a problem of the account.
        return $response->status() === 400 ? Result::failed($reason) : Result::errored($reason);
    }
}
