<?php

namespace App\LiveActivities;

use App\Models\LiveActivity;

/**
 * FCM data message of a Live Activity on Android ("carillon_live" key): the
 * SDK shows it as an ongoing notification with progress, promoted as a Live
 * Update on Android 16, replaced on each update and closed at the end.
 */
final class AndroidPayload
{
    /** Fields of the "android" object of the API requests. */
    public const DISPLAY = ['title', 'body', 'progress', 'progress_max', 'indeterminate', 'segments', 'chronometer', 'color', 'small_icon', 'short_text'];

    /**
     * @param  array<string, mixed>  $display
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function message(string $token, string $event, LiveActivity $activity, array $display, ?string $language, array $data): array
    {
        $live = array_filter([
            'e' => $event,
            'a' => $activity->activity_id,
            't' => $activity->activity_type,
            // Order of the updates (FCM does not guarantee it).
            'ts' => (int) floor(microtime(true) * 1000),
            'title' => LiveActivities::text($display['title'] ?? null, $language),
            'body' => LiveActivities::text($display['body'] ?? null, $language),
            'st' => LiveActivities::text($display['short_text'] ?? null, $language),
            'p' => isset($display['progress']) ? (int) $display['progress'] : null,
            'pm' => isset($display['progress_max']) ? max(1, (int) $display['progress_max']) : null,
            'pi' => ! empty($display['indeterminate']) ? 1 : null,
            'seg' => isset($display['segments']) ? min(20, max(1, (int) $display['segments'])) : null,
            'chr' => isset($display['chronometer']) ? (int) $display['chronometer'] : null,
            'color' => $display['color'] ?? null,
            'si' => $display['small_icon'] ?? null,
            'd' => $display['data'] ?? null,
            // End: when the final notification goes away (Unix seconds).
            'dis' => $event === 'end' && isset($data['dismissal_date']) ? (int) $data['dismissal_date'] : null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        $urgent = $event !== 'update' || (int) ($data['priority'] ?? 10) >= 10;

        return ['message' => [
            'token' => $token,
            'data' => ['carillon_live' => json_encode($live, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            'android' => [
                'priority' => $urgent ? 'HIGH' : 'NORMAL',
                // A late update is useless; a late start or end is not.
                'ttl' => $event === 'update' ? '3600s' : '28800s',
            ],
        ]];
    }
}
