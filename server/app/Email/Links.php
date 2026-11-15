<?php

namespace App\Email;

use Illuminate\Support\Facades\URL;

/**
 * Signed links of the emails: open pixel, tracked clicks, unsubscribe. The
 * path is signed (not the host), so links survive a change of address.
 */
final class Links
{
    public static function open(string $messageId, string $subscriptionId): string
    {
        return url(URL::signedRoute('email.open', ['message' => $messageId, 'subscription' => $subscriptionId], absolute: false));
    }

    public static function click(string $messageId, string $subscriptionId, string $url): string
    {
        return url(URL::signedRoute('email.click', ['message' => $messageId, 'subscription' => $subscriptionId, 'u' => $url], absolute: false));
    }

    public static function unsubscribe(string $subscriptionId, ?string $messageId = null): string
    {
        return url(URL::signedRoute('email.unsubscribe', array_filter(['subscription' => $subscriptionId, 'm' => $messageId]), absolute: false));
    }
}
