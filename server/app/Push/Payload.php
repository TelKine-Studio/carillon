<?php

namespace App\Push;

use App\Models\Message;

/**
 * Builds what each platform receives, from the OneSignal-style fields of a
 * message, in the language of the device.
 */
final class Payload
{
    /** Common part read by the SDKs ("carillon" key). @return array<string, mixed> */
    public static function common(Message $message, ?string $language): array
    {
        return array_filter([
            'i' => $message->id,
            'title' => Message::localized($message->headings, $language),
            'subtitle' => Message::localized($message->subtitle, $language),
            'body' => Message::localized($message->contents, $language),
            'd' => $message->option('data'),
            'u' => $message->option('app_url') ?? $message->option('url'),
            'img' => $message->option('big_picture') ?? $message->option('image'),
            'b' => $message->option('buttons'),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * APNs JSON body and headers.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function apns(Message $message, ?string $language, string $bundleId): array
    {
        $common = self::common($message, $language);
        $silent = (bool) $message->option('content_available') && empty($common['body']);

        $aps = $silent ? ['content-available' => 1] : array_filter([
            'alert' => array_filter([
                'title' => $common['title'] ?? null,
                'subtitle' => $common['subtitle'] ?? null,
                'body' => $common['body'] ?? null,
            ]),
            'sound' => $message->option('ios_sound', 'default') ?: null,
            'badge' => $message->option('ios_badgeType') === 'SetTo' ? (int) $message->option('ios_badgeCount', 0) : null,
            'thread-id' => $message->option('thread_id'),
            'category' => $message->option('ios_category'),
            'interruption-level' => $message->option('ios_interruption_level'),
            'relevance-score' => $message->option('ios_relevance_score'),
            // Lets the notification extension of the SDK add the image and
            // the buttons, and report the reception.
            'mutable-content' => 1,
            'content-available' => $message->option('content_available') ? 1 : null,
        ], fn ($value) => $value !== null && $value !== []);

        $ttl = $message->option('ttl');
        $headers = array_filter([
            'apns-topic' => $bundleId,
            'apns-push-type' => $silent ? 'background' : 'alert',
            'apns-priority' => $silent ? '5' : ((int) $message->option('priority', 10) >= 10 ? '10' : '5'),
            'apns-expiration' => $ttl !== null ? (string) ($ttl > 0 ? time() + (int) $ttl : 0) : null,
            'apns-collapse-id' => ($id = $message->option('collapse_id')) ? substr((string) $id, 0, 64) : null,
        ], fn ($value) => $value !== null);

        return [['aps' => $aps, 'carillon' => $common], $headers];
    }

    /**
     * FCM message (data only: the SDK builds the notification, with its
     * image, buttons and click tracking).
     *
     * @return array<string, mixed>
     */
    public static function fcm(Message $message, ?string $language, string $token): array
    {
        $common = array_filter(array_merge(self::common($message, $language), [
            'ch' => $message->option('android_channel_id'),
            'color' => $message->option('android_accent_color'),
            'si' => $message->option('small_icon'),
            'li' => $message->option('large_icon'),
            'g' => $message->option('android_group'),
            's' => $message->option('android_sound'),
            'v' => $message->option('android_visibility'),
            'silent' => (bool) $message->option('content_available') && ! Message::localized($message->contents, $language) ? 1 : null,
        ]), fn ($value) => $value !== null && $value !== '' && $value !== []);

        $ttl = $message->option('ttl');

        return ['message' => array_filter([
            'token' => $token,
            'data' => ['carillon' => json_encode($common, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            'android' => array_filter([
                'priority' => (int) $message->option('priority', 10) >= 10 ? 'HIGH' : 'NORMAL',
                'ttl' => $ttl !== null ? ((int) $ttl).'s' : null,
                'collapse_key' => $message->option('collapse_id'),
            ]),
        ])];
    }

    /** Web push payload read by the service worker. @return array<string, mixed> */
    public static function web(Message $message, ?string $language): array
    {
        $common = self::common($message, $language);

        return array_filter([
            'id' => $message->id,
            'title' => $common['title'] ?? null,
            'body' => $common['body'] ?? null,
            'icon' => $message->option('chrome_web_icon') ?? $message->option('firefox_icon'),
            'image' => $message->option('chrome_web_image') ?? ($common['img'] ?? null),
            'badge' => $message->option('chrome_web_badge'),
            'url' => $message->option('web_url') ?? $message->option('url') ?? $message->project->setting('default_url'),
            'data' => $common['d'] ?? null,
            'actions' => $message->option('web_buttons'),
            'tag' => $message->option('collapse_id') ?? $message->option('web_push_topic'),
            'requireInteraction' => $message->option('require_interaction') ? true : null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}
