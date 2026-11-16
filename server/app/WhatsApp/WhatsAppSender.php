<?php

namespace App\WhatsApp;

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
 * WhatsApp Cloud API (Meta): details {phone_number_id, business_account_id},
 * secret = access token of a system user. Outside the 24-hour window after a
 * reply of the person, only approved templates are delivered.
 */
class WhatsAppSender implements Sender
{
    public const API = 'https://graph.facebook.com/v23.0';

    protected const CONCURRENCY = 20;

    /** The number cannot receive WhatsApp messages from this business. */
    protected const DEAD = [131026, 130403, 131050];

    public function send(PushCredential $credential, Message $message, Collection $subscriptions): array
    {
        $url = self::API.'/'.$credential->detail('phone_number_id').'/messages';
        $results = [];

        foreach ($subscriptions->chunk(self::CONCURRENCY) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => $chunk->map(fn ($subscription) => $pool->as($subscription->id)
                ->withToken((string) $credential->secret)
                ->acceptJson()
                ->timeout(20)
                ->post($url, self::payload($message, $subscription)))->all());

            foreach ($chunk as $subscription) {
                $results[$subscription->id] = self::result($responses[$subscription->id] ?? null);
            }
        }

        return $results;
    }

    /** A template with its variables filled for the person, or a text. */
    public static function payload(Message $message, $subscription): array
    {
        $values = [
            'phone_number' => $subscription->token,
            'external_id' => $subscription->subscriber?->external_id,
            'tags' => $subscription->subscriber?->tags ?? [],
        ];
        $to = ltrim((string) $subscription->token, '+');

        if ($template = $message->option('whatsapp_template')) {
            $parameters = [];
            foreach ((array) $message->option('whatsapp_variables', []) as $name => $value) {
                $parameters[] = array_filter([
                    'type' => 'text',
                    'parameter_name' => is_string($name) ? $name : null,
                    'text' => EmailBuilder::render((string) $value, $values, false) ?: '-',
                ]);
            }

            return [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => array_filter([
                    'name' => $template,
                    'language' => ['code' => $message->option('whatsapp_language') ?: 'fr'],
                    'components' => $parameters ? [['type' => 'body', 'parameters' => $parameters]] : null,
                ]),
            ];
        }

        $text = (string) Message::localized($message->contents, $subscription->subscriber?->language);

        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => EmailBuilder::render($text, $values, false), 'preview_url' => true],
        ];
    }

    protected static function result(mixed $response): Result
    {
        if ($response instanceof Throwable || ! $response instanceof Response) {
            return Result::errored($response instanceof Throwable ? mb_substr($response->getMessage(), 0, 180) : 'No response from WhatsApp.');
        }
        if ($response->successful()) {
            return Result::sent();
        }

        $code = (int) $response->json('error.code');
        $reason = mb_substr(trim($code.' '.($response->json('error.error_data.details') ?? $response->json('error.message') ?? $response->reason())), 0, 180);

        if (in_array($code, self::DEAD, true)) {
            return Result::invalid($reason);
        }
        // This message only (24-hour window, per-user limit, template refused…).
        if (in_array($code, [131047, 131049, 131021, 132000, 132012], true)) {
            return Result::failed($reason);
        }

        return Result::errored($reason);
    }

    /**
     * Approved templates of the WhatsApp Business account, for the composer.
     *
     * @return list<array{name: string, language: string, category: ?string, body: string, variables: list<string>}>
     */
    public static function templates(PushCredential $credential): array
    {
        $account = $credential->detail('business_account_id');
        if (! $account) {
            return [];
        }

        $response = Http::withToken((string) $credential->secret)->acceptJson()->timeout(10)
            ->get(self::API."/{$account}/message_templates", ['fields' => 'name,language,status,category,components', 'limit' => 200]);
        if (! $response->successful()) {
            throw new \RuntimeException(mb_substr((string) ($response->json('error.message') ?? $response->status()), 0, 180));
        }

        return collect($response->json('data', []))
            ->where('status', 'APPROVED')
            ->map(function (array $template) {
                $body = (string) (collect($template['components'] ?? [])->firstWhere('type', 'BODY')['text'] ?? '');
                preg_match_all('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $body, $matches);

                return [
                    'name' => (string) $template['name'],
                    'language' => (string) $template['language'],
                    'category' => $template['category'] ?? null,
                    'body' => $body,
                    'variables' => array_values(array_unique($matches[1])),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }
}
