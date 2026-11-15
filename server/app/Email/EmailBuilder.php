<?php

namespace App\Email;

use App\Models\Message;
use App\Models\PushCredential;
use App\Models\Subscription;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * The email one person receives: personalized texts, tracked links and
 * open pixel (unless disabled), and an unsubscribe link (always).
 */
final class EmailBuilder
{
    public static function build(PushCredential $credential, Message $message, Subscription $subscription): Email
    {
        $language = $subscription->subscriber?->language;
        $unsubscribe = Links::unsubscribe($subscription->id, $message->id);
        $values = self::values($subscription, $unsubscribe);

        $subject = self::render(self::text($message->option('email_subject'), $language), $values, false);
        $html = self::render(self::text($message->option('email_body'), $language), $values, true);
        $preheader = self::render((string) self::text($message->option('email_preheader'), $language), $values, false);

        if (! str_contains((string) self::text($message->option('email_body'), $language), 'unsubscribe_url')) {
            $html = self::withFooter($html, $unsubscribe, $language);
        }
        $text = self::toText($html);

        if (self::tracking($credential, $message)) {
            $html = self::trackLinks($html, $message->id, $subscription->id);
            $html = self::insertBeforeBodyEnd($html, '<img src="'.e(Links::open($message->id, $subscription->id)).'" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0">');
        }
        if ($preheader !== '') {
            $html = self::insertAfterBodyStart($html, '<div style="display:none;max-height:0;overflow:hidden;opacity:0">'.e($preheader).str_repeat('&#847;&zwnj;&nbsp;', 30).'</div>');
        }

        $fromName = $message->option('email_from_name') ?: $credential->detail('from_name') ?: $message->project->name;
        $email = (new Email)
            ->from(new Address($message->option('email_from_address') ?: (string) $credential->detail('from_address'), (string) $fromName))
            ->to(new Address((string) $subscription->token))
            ->subject($subject !== '' ? $subject : $message->project->name)
            ->html($html)
            ->text($text);

        if ($replyTo = $message->option('email_reply_to_address') ?: $credential->detail('reply_to')) {
            $email->replyTo((string) $replyTo);
        }

        $headers = $email->getHeaders();
        // One-click unsubscribe (RFC 8058), required by Gmail and Yahoo.
        $headers->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        $headers->addTextHeader('X-Carillon-Message', $message->id);

        return $email;
    }

    /** @return array<string, mixed> */
    private static function values(Subscription $subscription, string $unsubscribe): array
    {
        return [
            'email' => $subscription->token,
            'external_id' => $subscription->subscriber?->external_id,
            'tags' => $subscription->subscriber?->tags ?? [],
            'unsubscribe_url' => $unsubscribe,
        ];
    }

    /** A text, or the text for a language in a {"en": ..., "fr": ...} map. */
    private static function text(mixed $value, ?string $language): ?string
    {
        return is_array($value) ? Message::localized($value, $language) : (is_string($value) ? $value : null);
    }

    /**
     * {{ email }}, {{ external_id }}, {{ tags.first_name }} (also tag.x),
     * {{ unsubscribe_url }}, with an optional fallback:
     * {{ tags.first_name | default: "there" }}.
     *
     * @param  array<string, mixed>  $values
     */
    public static function render(?string $template, array $values, bool $html): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)(?:\.([A-Za-z0-9_\-]+))?\s*(?:\|\s*default:\s*"([^"]*)")?\s*\}\}/',
            function (array $match) use ($values, $html) {
                $name = $match[1] === 'tag' ? 'tags' : $match[1];
                $value = isset($match[2]) && $match[2] !== '' ? ($values[$name][$match[2]] ?? null) : ($values[$name] ?? null);
                $value = is_scalar($value) && (string) $value !== '' ? (string) $value : ($match[3] ?? '');

                return $html && $name !== 'unsubscribe_url' ? e($value) : $value;
            },
            (string) $template,
        );
    }

    private static function tracking(PushCredential $credential, Message $message): bool
    {
        return $credential->detail('tracking', true) !== false && ! $message->option('disable_email_click_tracking');
    }

    private static function trackLinks(string $html, string $messageId, string $subscriptionId): string
    {
        return (string) preg_replace_callback('/(<a\b[^>]*\bhref\s*=\s*)(["\'])(.*?)\2/is', function (array $match) use ($messageId, $subscriptionId) {
            $url = html_entity_decode($match[3]);
            if (! preg_match('#^https?://#i', $url) || str_contains($url, '/e/u/')) {
                return $match[0];
            }

            return $match[1].$match[2].e(Links::click($messageId, $subscriptionId, $url)).$match[2];
        }, $html);
    }

    private static function withFooter(string $html, string $unsubscribe, ?string $language): string
    {
        $label = str_starts_with((string) $language, 'fr') ? 'Se désinscrire' : 'Unsubscribe';
        $footer = '<p style="margin:32px 0 0;font:12px/1.5 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#8a8a8a;text-align:center"><a href="'.e($unsubscribe).'" style="color:#8a8a8a">'.$label.'</a></p>';

        return self::insertBeforeBodyEnd($html, $footer);
    }

    private static function insertBeforeBodyEnd(string $html, string $snippet): string
    {
        return stripos($html, '</body>') !== false ? (string) preg_replace('#</body>#i', $snippet.'</body>', $html, 1) : $html.$snippet;
    }

    private static function insertAfterBodyStart(string $html, string $snippet): string
    {
        return preg_match('#<body[^>]*>#i', $html) ? (string) preg_replace('#(<body[^>]*>)#i', '$1'.$snippet, $html, 1) : $snippet.$html;
    }

    /** Plain text part, for mail apps without HTML (and spam filters). */
    public static function toText(string $html): string
    {
        $text = preg_replace('#<(style|script|head)\b[^>]*>.*?</\1>#is', '', $html);
        $text = preg_replace('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', '$2 ($1)', (string) $text);
        $text = preg_replace('#<(br|/p|/div|/h[1-6]|/li|/tr)\b[^>]*>#i', "\n", (string) $text);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = implode("\n", array_map('trim', explode("\n", (string) $text)));

        return trim((string) preg_replace("/\n\s*\n\s*\n+/", "\n\n", (string) $text));
    }
}
