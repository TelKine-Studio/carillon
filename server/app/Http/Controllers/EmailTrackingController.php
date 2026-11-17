<?php

namespace App\Http\Controllers;

use App\Audience\Events;
use App\Models\Subscription;
use App\Webhooks\Webhooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Public links of the emails (signed): open pixel, tracked clicks, unsubscribe. */
class EmailTrackingController extends Controller
{
    /** Transparent 1×1 GIF. */
    protected const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(string $message, string $subscription): Response
    {
        if ($device = $this->subscription($subscription)) {
            Events::record($device, $message, 'received');
        }

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    public function click(Request $request, string $message, string $subscription): RedirectResponse
    {
        $url = (string) $request->query('u');
        abort_unless(preg_match('#^https?://#i', $url), 404);

        if ($device = $this->subscription($subscription)) {
            Events::record($device, $message, 'clicked');
        }

        return redirect()->away($url);
    }

    public function unsubscribe(string $subscription): View
    {
        $device = $this->subscription($subscription);
        abort_unless($device, 404);

        return view('email.unsubscribe', ['subscription' => $device, 'done' => ! $device->enabled]);
    }

    /** Also the one-click unsubscribe of mail apps (List-Unsubscribe-Post). */
    public function confirm(Request $request, string $subscription): View|Response
    {
        $device = $this->subscription($subscription);
        abort_unless($device, 404);

        if ($request->boolean('resubscribe')) {
            $device->forceFill(['enabled' => true])->save();

            return view('email.unsubscribe', ['subscription' => $device, 'done' => false, 'resubscribed' => true]);
        }

        if ($device->enabled) {
            $device->forceFill(['enabled' => false])->setStatus('email_unsubscribed')->save();
            Webhooks::dispatch($device->project_id, 'subscription.unsubscribed', array_filter([
                'subscription_id' => $device->id,
                'subscriber_id' => $device->subscriber_id,
                'external_id' => $device->subscriber?->external_id,
                'platform' => 'email',
                'email' => $device->token,
                'reason' => 'unsubscribed',
                'message_id' => $request->query('m'),
            ], fn ($value) => $value !== null));
        }

        // Mail apps post without expecting a page.
        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('', 200);
        }

        return view('email.unsubscribe', ['subscription' => $device, 'done' => true]);
    }

    protected function subscription(string $id): ?Subscription
    {
        return Str::isUuid($id) ? Subscription::query()->with(['subscriber', 'project'])->where('type', 'email')->find($id) : null;
    }
}
