<?php

namespace App\Push;

use RuntimeException;

/** Minimal JWT signing for APNs (ES256) and Google OAuth (RS256). */
final class Jwt
{
    /** @param array<string, mixed> $header @param array<string, mixed> $claims */
    public static function sign(array $header, array $claims, string $privateKey, bool $ecdsa): string
    {
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new RuntimeException('Invalid private key.');
        }

        $segments = [self::base64Url(json_encode($header)), self::base64Url(json_encode($claims))];

        if (! openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign the token.');
        }

        $segments[] = self::base64Url($ecdsa ? self::derToRaw($signature) : $signature);

        return implode('.', $segments);
    }

    public static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** DER encoded ECDSA signature to the raw R|S form expected by ES256. */
    public static function derToRaw(string $der): string
    {
        $offset = 2;
        if (ord($der[1]) & 0x80) {
            $offset += ord($der[1]) & 0x7F;
        }

        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $parts[] = str_pad(ltrim(substr($der, $offset + 2, $length), "\x00"), 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return implode('', $parts);
    }
}
