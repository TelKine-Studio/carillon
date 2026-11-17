<?php

namespace App\Integrations;

use App\Models\Integration;

/**
 * Turns a Carillon event into the request of an analytics or chat tool:
 * PostHog capture, Mixpanel track, Amplitude HTTP V2, Slack and Discord
 * incoming webhooks.
 */
final class Streams
{
    public const POSTHOG_HOSTS = ['us' => 'https://us.i.posthog.com', 'eu' => 'https://eu.i.posthog.com'];

    public const MIXPANEL_HOSTS = ['us' => 'https://api.mixpanel.com', 'eu' => 'https://api-eu.mixpanel.com', 'in' => 'https://api-in.mixpanel.com'];

    public const AMPLITUDE_HOSTS = ['us' => 'https://api2.amplitude.com', 'eu' => 'https://api.eu.amplitude.com'];

    /**
     * @param  array{id: string, type: string, created_at: string, data: array<string, mixed>}  $payload
     * @return array{url: string, body: array<string, mixed>, form?: bool}
     */
    public static function request(Integration $integration, array $payload, string $projectName): array
    {
        $data = $payload['data'] ?? [];
        $event = self::eventName($integration, $payload['type']);
        $user = self::distinctId($data);
        $properties = array_filter($data + ['carillon_event_id' => $payload['id'], 'carillon_project' => $projectName], fn ($v) => $v !== null && $v !== '');
        $time = strtotime($payload['created_at']) ?: time();

        return match ($integration->kind) {
            'posthog' => [
                'url' => self::host($integration, self::POSTHOG_HOSTS).'/i/v0/e/',
                'body' => [
                    'api_key' => $integration->secret,
                    'event' => $event,
                    'distinct_id' => $user,
                    'properties' => $properties + ['$insert_id' => $payload['id']],
                    'timestamp' => $payload['created_at'],
                ],
            ],
            'mixpanel' => [
                'url' => self::host($integration, self::MIXPANEL_HOSTS).'/track',
                'form' => true,
                'body' => [
                    'data' => json_encode(['event' => $event, 'properties' => $properties + [
                        'token' => $integration->secret,
                        'distinct_id' => $user,
                        'time' => $time,
                        '$insert_id' => str_replace('-', '', $payload['id']),
                    ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'verbose' => 1,
                    'ip' => 0,
                ],
            ],
            'amplitude' => [
                'url' => self::host($integration, self::AMPLITUDE_HOSTS).'/2/httpapi',
                'body' => [
                    'api_key' => $integration->secret,
                    'events' => [array_filter([
                        'user_id' => $user,
                        'event_type' => $event,
                        'time' => $time * 1000,
                        'insert_id' => $payload['id'],
                        'platform' => $data['platform'] ?? null,
                        'event_properties' => $properties,
                    ], fn ($v) => $v !== null)],
                    'options' => ['min_id_length' => 1],
                ],
            ],
            'slack' => ['url' => (string) $integration->secret, 'body' => ['text' => self::sentence($payload['type'], $data, $projectName)]],
            'discord' => ['url' => (string) $integration->secret, 'body' => ['content' => self::sentence($payload['type'], $data, $projectName), 'allowed_mentions' => ['parse' => []]]],
        };
    }

    /** "notification.clicked" → "Notification clicked", with the prefix chosen. */
    public static function eventName(Integration $integration, string $type): string
    {
        $name = ucfirst(str_replace(['.', '_'], ' ', $type));

        return trim(($integration->setting('prefix') ?? 'Carillon').' '.$name);
    }

    /** The person in the analytics tool: the external id of the app, else the ids of Carillon. */
    public static function distinctId(array $data): string
    {
        return (string) ($data['external_id'] ?? $data['subscriber_id'] ?? $data['subscription_id'] ?? 'carillon');
    }

    /** One line for a chat channel. */
    public static function sentence(string $type, array $data, string $project): string
    {
        $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
        $who = $data['external_id'] ?? null;

        return '['.$project.'] '.match ($type) {
            'message.sent' => __('Message “:name” sent: :ok delivered, :failed failed out of :total.', [
                'name' => $data['name'] ?? $data['message_id'] ?? '?',
                'ok' => $n($data['successful'] ?? 0),
                'failed' => $n(($data['failed'] ?? 0) + ($data['errored'] ?? 0)),
                'total' => $n($data['recipients'] ?? 0),
            ]).(isset($data['error']) ? ' '.$data['error'] : ''),
            'notification.received' => __('Notification received on :platform', ['platform' => $data['platform'] ?? '?']).($who ? ' ('.$who.')' : '').'.',
            'notification.clicked' => __('Notification clicked on :platform', ['platform' => $data['platform'] ?? '?']).($who ? ' ('.$who.')' : '').'.',
            'email.opened' => __('Email opened').($who ? ' ('.$who.')' : '').'.',
            'email.clicked' => __('Link clicked in an email').($who ? ' ('.$who.')' : '').'.',
            'subscription.created' => __('New :platform device', ['platform' => $data['platform'] ?? '']).($who ? ' ('.$who.')' : '').'.',
            'subscription.unsubscribed' => __(':platform device unsubscribed', ['platform' => ucfirst($data['platform'] ?? '')]).($who ? ' ('.$who.')' : '').'.',
            'onesignal_sync.failed' => __('The nightly OneSignal import failed: :error', ['error' => $data['error'] ?? '?']),
            'integration.failed' => __('The integration “:name” failed: :error', ['name' => $data['name'] ?? '?', 'error' => $data['error'] ?? '?']),
            'webhook.test', 'integration.test' => __('Test message from Carillon: this channel receives the events of the project.'),
            default => $type,
        };
    }

    /** @param array<string, string> $hosts */
    protected static function host(Integration $integration, array $hosts): string
    {
        $host = $integration->setting('host');
        if ($integration->setting('region') === 'custom' && is_string($host) && str_starts_with($host, 'http')) {
            return rtrim($host, '/');
        }

        return $hosts[$integration->setting('region', 'us')] ?? reset($hosts);
    }
}
