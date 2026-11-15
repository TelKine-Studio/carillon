<?php

namespace App\Email;

use Closure;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * Delivers to the mail servers of the recipients (their MX records), on
 * port 25, without any third party. TLS is used when the server offers it
 * (opportunistic, as between mail servers).
 */
final class DirectTransport implements TransportInterface
{
    /**
     * @param  Closure(string): list<string>|null  $resolver  domain => mail servers, by priority
     * @param  Closure(string): TransportInterface|null  $connector  mail server => transport
     */
    public function __construct(
        private string $helo,
        private ?Closure $resolver = null,
        private ?Closure $connector = null,
    ) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $envelope ??= Envelope::create($message);
        $byDomain = [];
        foreach ($envelope->getRecipients() as $recipient) {
            $byDomain[strtolower(substr(strrchr($recipient->getAddress(), '@') ?: '@', 1))][] = $recipient;
        }

        $sent = null;
        foreach ($byDomain as $domain => $recipients) {
            $sent = $this->deliver($message, new Envelope($envelope->getSender(), $recipients), $domain);
        }

        return $sent;
    }

    /** @param list<Address> $recipients */
    private function deliver(RawMessage $message, Envelope $envelope, string $domain): ?SentMessage
    {
        $hosts = $this->resolve($domain);
        if ($hosts === []) {
            throw new UnexpectedResponseException('No mail server for the domain '.$domain.'.', 550);
        }

        $last = null;
        foreach ($hosts as $host) {
            try {
                return $this->connect($host)->send($message, $envelope);
            } catch (UnexpectedResponseException $e) {
                // A refusal (5xx) would be the same on the backup servers.
                if ($e->getCode() >= 500) {
                    throw $e;
                }
                $last = $e;
            } catch (TransportExceptionInterface $e) {
                $last = $e;
            }
        }

        throw $last ?? new TransportException('Delivery failed.');
    }

    /** @return list<string> */
    private function resolve(string $domain): array
    {
        if ($this->resolver) {
            return ($this->resolver)($domain);
        }

        $hosts = $weights = [];
        if (getmxrr($domain, $hosts, $weights) && $hosts !== []) {
            array_multisort($weights, SORT_ASC, $hosts);

            // "Null MX" (RFC 7505): the domain accepts no email.
            return array_values(array_filter($hosts, fn ($host) => $host !== '' && $host !== '.'));
        }

        // No MX: the domain itself, if it exists.
        return checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA') ? [$domain] : [];
    }

    private function connect(string $host): TransportInterface
    {
        if ($this->connector) {
            return ($this->connector)($host);
        }

        $transport = new EsmtpTransport($host, 25, false);
        $transport->setLocalDomain($this->helo);
        $transport->setAutoTls(true);
        $stream = $transport->getStream();
        $stream->setTimeout(20);
        $stream->setStreamOptions(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);

        return $transport;
    }

    public function __toString(): string
    {
        return 'direct://'.$this->helo;
    }
}
