<?php

namespace App\LiveActivities;

use App\Models\PushCredential;

/** APNs requests of Live Activities (push type "liveactivity"). */
final class Payload
{
    /** Fields of an update or end kept while the update token is awaited. */
    public const KEYS = ['event_updates', 'contents', 'headings', 'priority', 'stale_date', 'dismissal_date', 'ios_sound', 'ios_relevance_score', 'android', 'data'];

    /**
     * Push-to-start: the attributes carry the activity_id under "carillon",
     * so that the app reports the update token of this very activity.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $state
     * @return array{body: array<string, mixed>, headers: array<string, string>}
     */
    public static function start(PushCredential $credential, string $type, string $activityId, array $data, array $state, ?string $language): array
    {
        $attributes = is_array($data['event_attributes'] ?? null) ? $data['event_attributes'] : [];
        $attributes['carillon'] = ['activityId' => $activityId];

        $aps = [
            'timestamp' => time(),
            'event' => 'start',
            'content-state' => (object) $state,
            'attributes-type' => $type,
            'attributes' => (object) $attributes,
            // iOS shows the start as an alert: required by Apple.
            'alert' => self::alert($data, $language) ?? ['title' => (string) ($data['name'] ?? $type)],
            ...self::common($data),
        ];

        return ['body' => ['aps' => $aps], 'headers' => self::headers($credential, 10)];
    }

    /**
     * Update or end.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $state
     * @return array{body: array<string, mixed>, headers: array<string, string>}
     */
    public static function event(PushCredential $credential, string $event, array $data, array $state, ?string $language): array
    {
        $aps = array_filter([
            'timestamp' => time(),
            'event' => $event,
            'content-state' => (object) $state,
            'alert' => self::alert($data, $language),
            ...self::common($data),
        ], fn ($value) => $value !== null);

        // Same default as OneSignal: 10, which counts against the budget
        // Apple gives each device; 5 is delivered when convenient, unlimited.
        $priority = $event === 'end' ? 10 : ((int) ($data['priority'] ?? 10) >= 10 ? 10 : 5);

        return ['body' => ['aps' => $aps], 'headers' => self::headers($credential, $priority)];
    }

    /** @return array<string, mixed> */
    protected static function common(array $data): array
    {
        return array_filter([
            'stale-date' => isset($data['stale_date']) ? (int) $data['stale_date'] : null,
            'dismissal-date' => isset($data['dismissal_date']) ? (int) $data['dismissal_date'] : null,
            'relevance-score' => isset($data['ios_relevance_score']) ? (float) $data['ios_relevance_score'] : null,
        ], fn ($value) => $value !== null);
    }

    /** @return array<string, string>|null */
    protected static function alert(array $data, ?string $language): ?array
    {
        $alert = array_filter([
            'title' => LiveActivities::text($data['headings'] ?? null, $language),
            'body' => LiveActivities::text($data['contents'] ?? null, $language),
            'sound' => isset($data['ios_sound']) ? (string) $data['ios_sound'] : null,
        ]);

        return isset($alert['title']) || isset($alert['body']) ? $alert : null;
    }

    /** @return array<string, string> */
    protected static function headers(PushCredential $credential, int $priority): array
    {
        return [
            'apns-topic' => $credential->detail('bundle_id').'.push-type.liveactivity',
            'apns-push-type' => 'liveactivity',
            'apns-priority' => (string) $priority,
        ];
    }
}
