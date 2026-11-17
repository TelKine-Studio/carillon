<?php

namespace App\Support;

/**
 * Absolute links sent by email (password reset, invitations): built on the
 * URL of the instance (Settings → Instance) when it is set, never on the Host
 * header of the request, which an attacker chooses.
 */
final class AppUrl
{
    public static function to(string $path): string
    {
        $base = app(Settings::class)->get('app.url');
        if (! is_string($base) || ! str_starts_with($base, 'http')) {
            return url($path);
        }

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    public static function route(string $name, mixed $parameters = []): string
    {
        return self::to(route($name, $parameters, false));
    }
}
