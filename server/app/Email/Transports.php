<?php

namespace App\Email;

use App\Models\PushCredential;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/** Builds the Symfony Mailer transport of a project's email settings. */
final class Transports
{
    public static function make(PushCredential $credential): TransportInterface
    {
        $driver = $credential->detail('driver');
        $secret = (string) $credential->secret;

        if ($driver === 'smtp') {
            $encryption = $credential->detail('encryption', 'tls');
            $transport = new EsmtpTransport((string) $credential->detail('host'), (int) $credential->detail('port', 587), $encryption === 'ssl');
            // tls: STARTTLS when offered (required by most servers); none: plain.
            $transport->setAutoTls($encryption !== 'none');
            if (filled($credential->detail('username'))) {
                $transport->setUsername((string) $credential->detail('username'));
                $transport->setPassword($secret);
            }

            return $transport;
        }

        if ($driver === 'direct') {
            return new DirectTransport(
                (string) $credential->detail('helo') ?: (string) $credential->detail('dkim_domain'),
            );
        }

        $dsn = match ($driver) {
            'resend' => new Dsn('resend+api', 'default', $secret),
            'ses' => new Dsn('ses+api', 'default', (string) $credential->detail('key_id'), $secret, null, ['region' => (string) $credential->detail('region')]),
            'postmark' => new Dsn('postmark+api', 'default', $secret, null, null, array_filter(['message_stream' => $credential->detail('message_stream')])),
            'mailgun' => new Dsn('mailgun+api', 'default', $secret, (string) $credential->detail('domain'), null, ['region' => (string) $credential->detail('region', 'us')]),
        };

        return (new Transport(iterator_to_array(Transport::getDefaultFactories())))->fromDsnObject($dsn);
    }
}
