<?php

namespace App\Sms;

/** Phone numbers in the E.164 format (+22507000000) that SMS providers expect. */
final class Phone
{
    /** "+225 07 00 00 00 00", "00225…" → "+2250700000000"; null if it is not a number. */
    public static function normalize(string $value): ?string
    {
        $digits = preg_replace('/[\s().\-]/', '', trim($value)) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        return preg_match('/^\+[1-9]\d{6,14}$/', $digits) ? $digits : null;
    }

    /** The GSM 7-bit alphabet (with its extension table): other characters make parts of 70. */
    public const GSM = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà^{}\\[~]|€";

    /** Number of SMS a text is split into. */
    public static function segments(string $text): int
    {
        $length = mb_strlen($text);
        if ($length === 0) {
            return 0;
        }
        $gsm = collect(mb_str_split($text))->every(fn (string $char) => str_contains(self::GSM, $char));

        return $gsm ? ($length <= 160 ? 1 : (int) ceil($length / 153)) : ($length <= 70 ? 1 : (int) ceil($length / 67));
    }
}
