<?php

namespace App\Email;

use App\Models\Message;
use App\Models\PushCredential;
use App\Push\Result;
use App\Push\Sender;
use Illuminate\Support\Collection;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Throwable;

/** Sends a message by email, one person at a time, at the rate of the service. */
class EmailSender implements Sender
{
    /** @var (callable(PushCredential): TransportInterface)|null for tests */
    public static $transportFactory = null;

    public function send(PushCredential $credential, Message $message, Collection $subscriptions): array
    {
        $transport = self::$transportFactory ? (self::$transportFactory)($credential) : Transports::make($credential);
        $signer = $credential->detail('driver') === 'direct'
            ? new DkimSigner((string) $credential->secret, (string) $credential->detail('dkim_domain'), (string) $credential->detail('dkim_selector'))
            : null;
        $interval = 1 / Drivers::rate($credential);
        $results = [];

        try {
            foreach ($subscriptions as $subscription) {
                $started = microtime(true);
                $results[$subscription->id] = $this->deliver($transport, $signer, $credential, $message, $subscription);

                $wait = $interval - (microtime(true) - $started);
                if ($wait > 0 && ! self::$transportFactory) {
                    usleep((int) ($wait * 1_000_000));
                }
            }
        } finally {
            if ($transport instanceof SmtpTransport) {
                $transport->stop();
            }
        }

        return $results;
    }

    private function deliver(TransportInterface $transport, ?DkimSigner $signer, PushCredential $credential, Message $message, $subscription): Result
    {
        try {
            $email = EmailBuilder::build($credential, $message, $subscription);
            $transport->send($signer ? $signer->sign($email) : $email);

            return Result::sent();
        } catch (UnexpectedResponseException $e) {
            return self::fromSmtp($e->getCode(), $e->getMessage());
        } catch (HttpTransportException $e) {
            $status = $e->getResponse()->getStatusCode();
            $reason = mb_substr($e->getMessage(), 0, 180);

            // The service refuses this address (invalid, suppressed...).
            return $status === 422 || ($status >= 400 && $status < 500 && preg_match('/inactive|invalid|suppress|not.+valid|recipient/i', $reason))
                ? Result::failed($reason)
                : Result::errored($reason);
        } catch (TransportExceptionInterface $e) {
            return Result::errored(mb_substr($e->getMessage(), 0, 180));
        } catch (Throwable $e) {
            return Result::errored(mb_substr($e->getMessage(), 0, 180));
        }
    }

    /** 550 5.1.1 (no such mailbox): the address is dead; other refusals are for this message. */
    public static function fromSmtp(int $code, string $message): Result
    {
        $reason = mb_substr(trim(preg_replace('/\s+/', ' ', $message)), 0, 180);
        if ($code >= 500) {
            return preg_match('/\b5\.1\.[0-3]\b|user unknown|no such user|does not exist|mailbox unavailable|recipient.+rejected|address rejected|no mail server/i', $message)
                ? Result::invalid($reason)
                : Result::failed($reason);
        }

        return Result::errored($reason);
    }
}
