<?php

namespace App\Push;

/**
 * Web push subscriptions come from browsers: their endpoint is always a URL
 * of the push service of the browser. Any other address (an internal one,
 * a server of the attacker) is refused, so registering a "browser" cannot
 * make Carillon call arbitrary hosts.
 */
final class WebPushEndpoint
{
    /** Host suffixes of the push services of Chrome, Edge, Firefox, Safari, Opera, Samsung… */
    public const HOSTS = [
        'fcm.googleapis.com', 'android.googleapis.com', 'jmt17.google.com',
        'push.services.mozilla.com', 'notify.windows.com', 'push.apple.com',
    ];

    public static function allowed(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['port'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        foreach (self::HOSTS as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }
}
