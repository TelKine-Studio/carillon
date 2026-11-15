<?php

namespace App\Email;

use App\Models\PushCredential;

/**
 * The ways an instance can deliver its emails, chosen per project:
 * an email service (fast, reliable deliverability), any SMTP server, or
 * direct delivery to the recipients' mail servers (no third party).
 */
final class Drivers
{
    /** Driver => fields of `details` it needs (the secret is always needed). */
    public const ALL = [
        'smtp' => ['host', 'port'],
        'resend' => [],
        'ses' => ['key_id', 'region'],
        'postmark' => [],
        'mailgun' => ['domain', 'region'],
        'direct' => ['dkim_domain', 'dkim_selector'],
    ];

    /** Sending rate per second by default, under each service's usual limit. */
    public const DEFAULT_RATE = ['smtp' => 10, 'resend' => 2, 'ses' => 14, 'postmark' => 25, 'mailgun' => 25, 'direct' => 5];

    /** Services that report bounces and complaints to a webhook. */
    public const WEBHOOKS = ['resend', 'ses', 'postmark', 'mailgun'];

    public static function usable(PushCredential $credential): bool
    {
        $driver = $credential->detail('driver');
        if (! isset(self::ALL[$driver]) || blank($credential->detail('from_address'))) {
            return false;
        }
        if ($driver !== 'smtp' && blank($credential->secret)) {
            return false;
        }

        foreach (self::ALL[$driver] as $field) {
            if (blank($credential->detail($field))) {
                return false;
            }
        }

        return true;
    }

    public static function rate(PushCredential $credential): int
    {
        return max(1, (int) ($credential->detail('rate') ?: self::DEFAULT_RATE[$credential->detail('driver')] ?? 5));
    }
}
