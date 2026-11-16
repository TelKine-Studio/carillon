<?php

namespace App\LiveActivities;

/**
 * The standard Live Activity of Carillon ("live" object of the API): one
 * description shown by the ready-made widget on iOS
 * (CarillonActivityAttributes) and as a Live Update on Android.
 *
 *   {"name": "MaRecharge", "icon": "bolt.fill", "color": "#0F766E",
 *    "stale_label": "Updating…", "title": "Top-up in progress",
 *    "body": "1 000 F to Orange", "status": "2/3", "step": 2, "steps": 3,
 *    "progress": 0.6, "state": "active|success|failure"}
 */
final class Template
{
    /** activity_type of the standard activity (name of the Swift struct). */
    public const TYPE = 'CarillonActivityAttributes';

    /** Fixed for the life of the activity (attributes). */
    public const ATTRIBUTES = ['name', 'icon', 'color', 'stale_label'];

    /** Updated along the way (content state). */
    public const STATE = ['title', 'body', 'status', 'progress', 'step', 'steps', 'state'];

    /**
     * Fills event_attributes, event_updates, android and the alert of a
     * start from the "live" object (what the request gives itself wins).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function start(array $data): array
    {
        $live = self::live($data);
        if ($live === null) {
            return $data;
        }

        $attributes = array_filter([
            'name' => $live['name'] ?? null,
            'icon' => $live['icon'] ?? null,
            'color' => $live['color'] ?? null,
            'staleLabel' => $live['stale_label'] ?? null,
        ], fn ($value) => $value !== null);

        return [
            ...$data,
            'event_attributes' => [...$attributes, ...(array) ($data['event_attributes'] ?? [])],
            'event_updates' => [...self::state([], $live), ...(array) ($data['event_updates'] ?? [])],
            'android' => [...self::android([], $live), ...(array) ($data['android'] ?? [])],
            'headings' => $data['headings'] ?? (isset($live['title']) ? ['en' => (string) $live['title']] : null),
            'contents' => $data['contents'] ?? (isset($live['body']) ? ['en' => (string) $live['body']] : null),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function live(array $data): ?array
    {
        return is_array($data['live'] ?? null) ? $data['live'] : null;
    }

    /**
     * Content state of iOS: the previous one with what changes.
     *
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $live
     * @return array<string, mixed>
     */
    public static function state(array $previous, array $live): array
    {
        $state = [...$previous];
        foreach (self::STATE as $key) {
            if (array_key_exists($key, $live)) {
                $state[$key] = match ($key) {
                    'progress' => $live[$key] === null ? null : max(0, min(1, (float) $live[$key])),
                    'step', 'steps' => $live[$key] === null ? null : (int) $live[$key],
                    default => $live[$key] === null ? null : (string) $live[$key],
                };
            }
        }

        return array_filter($state, fn ($value) => $value !== null);
    }

    /**
     * Android display (Live Update) of the same state: steps become a
     * segmented bar, progress a bar out of 100, status the chip.
     *
     * @param  array<string, mixed>  $previous  previous display of the device
     * @param  array<string, mixed>  $live
     * @return array<string, mixed>
     */
    public static function android(array $previous, array $live): array
    {
        $display = array_filter([
            'title' => $live['title'] ?? null,
            'body' => $live['body'] ?? null,
            'short_text' => $live['status'] ?? null,
            'color' => $live['color'] ?? null,
        ], fn ($value) => $value !== null);

        $steps = $live['steps'] ?? ($previous['segments'] ?? null);
        if (isset($live['step']) && $steps) {
            $display += ['progress' => (int) $live['step'], 'progress_max' => (int) $steps, 'segments' => (int) $steps];
        } elseif (isset($live['progress'])) {
            $display += ['progress' => (int) round(max(0, min(1, (float) $live['progress'])) * 100), 'progress_max' => 100];
        }

        return $display;
    }
}
