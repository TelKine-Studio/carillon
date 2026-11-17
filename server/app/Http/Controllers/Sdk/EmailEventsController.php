<?php

namespace App\Http\Controllers\Sdk;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Subscription;
use App\Webhooks\Webhooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Bounces and complaints reported by the email service, at the address
 * shown in the email settings (its secret part authenticates the service):
 * a hard bounce disables the address, a complaint unsubscribes it.
 */
class EmailEventsController extends Controller
{
    public function store(Request $request, string $appId, string $provider, string $secret): JsonResponse
    {
        $project = Project::query()->with('credentials')->where('app_id', $appId)->first();
        $credential = $project?->credential('email');
        abort_unless($credential && hash_equals((string) $credential->detail('webhook_secret'), $secret), 404);

        $events = match ($provider) {
            'resend' => $this->resend($request),
            'postmark' => $this->postmark($request),
            'mailgun' => $this->mailgun($request),
            'ses' => $this->ses($request),
            default => abort(404),
        };

        foreach ($events as [$email, $kind]) {
            $this->apply($project, mb_strtolower(trim((string) $email)), $kind);
        }

        return response()->json(['success' => true]);
    }

    /** @return list<array{0: string, 1: string}> */
    protected function resend(Request $request): array
    {
        $kind = match ($request->input('type')) {
            'email.bounced' => $request->input('data.bounce.type', 'Permanent') === 'Permanent' ? 'bounced' : null,
            'email.complained' => 'complained',
            default => null,
        };

        return $kind ? array_map(fn ($to) => [$to, $kind], (array) $request->input('data.to', [])) : [];
    }

    /** @return list<array{0: string, 1: string}> */
    protected function postmark(Request $request): array
    {
        return match ($request->input('RecordType')) {
            'Bounce' => in_array($request->input('Type'), ['HardBounce', 'BadEmailAddress', 'ManuallyDeactivated'], true) ? [[$request->input('Email'), 'bounced']] : [],
            'SpamComplaint' => [[$request->input('Email'), 'complained']],
            'SubscriptionChange' => $request->boolean('SuppressSending') ? [[$request->input('Recipient'), 'unsubscribed']] : [],
            default => [],
        };
    }

    /** @return list<array{0: string, 1: string}> */
    protected function mailgun(Request $request): array
    {
        $event = (array) $request->input('event-data', []);

        return match ($event['event'] ?? null) {
            'failed' => ($event['severity'] ?? null) === 'permanent' ? [[$event['recipient'] ?? '', 'bounced']] : [],
            'complained' => [[$event['recipient'] ?? '', 'complained']],
            'unsubscribed' => [[$event['recipient'] ?? '', 'unsubscribed']],
            default => [],
        };
    }

    /** Amazon SES through SNS (the subscription is confirmed automatically). */
    protected function ses(Request $request): array
    {
        $body = json_decode($request->getContent(), true) ?: [];

        if (($body['Type'] ?? null) === 'SubscriptionConfirmation') {
            $url = (string) ($body['SubscribeURL'] ?? '');
            if (preg_match('#^https://sns\.[a-z0-9-]+\.amazonaws\.com/#', $url)) {
                Http::timeout(10)->get($url);
            }

            return [];
        }

        $message = json_decode((string) ($body['Message'] ?? ''), true) ?: [];

        return match ($message['notificationType'] ?? $message['eventType'] ?? null) {
            'Bounce' => ($message['bounce']['bounceType'] ?? null) === 'Permanent'
                ? array_map(fn ($r) => [$r['emailAddress'] ?? '', 'bounced'], $message['bounce']['bouncedRecipients'] ?? [])
                : [],
            'Complaint' => array_map(fn ($r) => [$r['emailAddress'] ?? '', 'complained'], $message['complaint']['complainedRecipients'] ?? []),
            default => [],
        };
    }

    protected function apply(Project $project, string $email, string $kind): void
    {
        if ($email === '') {
            return;
        }

        $subscription = Subscription::query()->with('subscriber')
            ->where('project_id', $project->id)
            ->where('type', 'email')
            ->where('token_hash', hash('sha256', $email))
            ->first();
        // Already handled (services may report an event twice).
        if (! $subscription || ($kind === 'bounced' ? $subscription->invalid_at : ! $subscription->enabled)) {
            return;
        }

        $subscription->forceFill($kind === 'bounced' ? ['invalid_at' => now()] : ['enabled' => false])
            ->setStatus($kind === 'bounced' ? 'email_bounced' : 'email_complaint')
            ->save();

        Webhooks::dispatch($project, 'subscription.unsubscribed', array_filter([
            'subscription_id' => $subscription->id,
            'subscriber_id' => $subscription->subscriber_id,
            'external_id' => $subscription->subscriber?->external_id,
            'platform' => 'email',
            'email' => $email,
            'reason' => $kind,
        ], fn ($value) => $value !== null));
    }
}
