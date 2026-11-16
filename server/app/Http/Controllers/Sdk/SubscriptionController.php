<?php

namespace App\Http\Controllers\Sdk;

use App\Audience\Conversions;
use App\Audience\Events;
use App\Audience\Identity;
use App\Http\Controllers\Controller;
use App\Journeys\JourneyRunner;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Webhooks\Webhooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Endpoints called by the SDKs of the apps (no API key: the App ID is
 * public, and the subscription id returned at registration identifies the
 * device afterwards).
 */
class SubscriptionController extends Controller
{
    public function __construct(protected Identity $identity) {}

    /** Registers a device, or updates it if its token is already known. */
    public function store(Request $request, string $appId): JsonResponse
    {
        $project = $this->project($appId);
        $data = $request->validate([
            'type' => ['required', Rule::in(Subscription::TYPES)],
            'token' => ['required', 'string', 'max:4000'],
            ...$this->deviceRules(),
            'external_id' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:'.Subscriber::MAX_TAGS],
        ]);

        $token = $this->normalizeToken($data['type'], $data['token']);

        $created = false;
        $subscription = DB::transaction(function () use ($project, $data, $token, &$created) {
            $subscription = Subscription::query()
                ->where('project_id', $project->id)
                ->where('token_hash', hash('sha256', $token))
                ->first();

            if (! $subscription) {
                $created = true;
                $subscriber = $this->identity->newSubscriber($project, ['last_active_at' => now()]);
                $subscription = new Subscription(['project_id' => $project->id, 'subscriber_id' => $subscriber->id, 'type' => $data['type']]);
            }

            $subscription->fill(['token' => $token, 'invalid_at' => null]);
            $this->applyDevice($subscription, $data);
            $subscription->save();

            return $subscription;
        });

        if (filled($data['external_id'] ?? null)) {
            $this->identity->login($subscription, $data['external_id']);
        }

        $subscriber = $subscription->fresh()->subscriber;
        $tagsBefore = $subscriber->tags;
        $this->applyPerson($subscriber, $data);

        $journeys = app(JourneyRunner::class);
        $journeys->tagsChanged($subscriber->fresh(), $tagsBefore);
        if ($created) {
            Webhooks::dispatch($project, 'subscription.created', $this->webhookData($subscription->fresh('subscriber')));
            $journeys->trigger($project, 'new_device', $subscriber);
        }

        return response()->json($this->present($subscription->fresh('subscriber')), 201);
    }

    /** Updates the device: new token, permission change, new session... */
    public function update(Request $request, string $appId, string $id): JsonResponse
    {
        $subscription = $this->subscription($appId, $id);
        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:4000'],
            ...$this->deviceRules(),
            'session' => ['nullable', 'boolean'],
        ]);

        if (filled($data['token'] ?? null)) {
            $subscription->token = $this->normalizeToken($subscription->type, $data['token']);
            $subscription->invalid_at = null;
        }
        $wasEnabled = $subscription->enabled;
        $this->applyDevice($subscription, $data);

        if ($request->boolean('session')) {
            $subscription->sessions++;
            $subscription->last_active_at = now();
        }
        $subscription->save();
        $this->applyPerson($subscription->subscriber, $data);

        if ($wasEnabled && ! $subscription->enabled) {
            Webhooks::dispatch($subscription->project_id, 'subscription.unsubscribed', $this->webhookData($subscription, 'opted_out'));
        }

        return response()->json($this->present($subscription->fresh('subscriber')));
    }

    public function login(Request $request, string $appId, string $id): JsonResponse
    {
        $subscription = $this->subscription($appId, $id);
        $data = $request->validate(['external_id' => ['required', 'string', 'max:255']]);

        $this->identity->login($subscription, $data['external_id']);

        return response()->json($this->present($subscription->fresh('subscriber')));
    }

    public function logout(string $appId, string $id): JsonResponse
    {
        $subscription = $this->subscription($appId, $id);
        $this->identity->logout($subscription);

        return response()->json($this->present($subscription->fresh('subscriber')));
    }

    /** Adds, changes or (with null) removes tags of the person. */
    public function tags(Request $request, string $appId, string $id): JsonResponse
    {
        $subscription = $this->subscription($appId, $id);
        $data = $request->validate(['tags' => ['present', 'array', 'max:'.Subscriber::MAX_TAGS]]);

        $subscriber = $subscription->subscriber;
        $before = $subscriber->tags;
        $subscriber->mergeTags($data['tags']);
        $subscriber->save();
        app(JourneyRunner::class)->tagsChanged($subscriber, $before);

        return response()->json(['tags' => (object) ($subscriber->tags ?? [])]);
    }

    /** Reception (shown on the device) or click of a notification. */
    /** Custom event of the app (trackEvent, outcomes): attributed to the last message. */
    public function customEvent(Request $request, string $appId, string $id): JsonResponse
    {
        $subscription = $this->subscription($appId, $id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'value' => ['nullable', 'numeric'],
            'properties' => ['nullable', 'array', 'max:50'],
        ]);

        app(Conversions::class)->record(
            $subscription->project,
            $subscription->subscriber,
            $data['name'],
            isset($data['value']) ? (float) $data['value'] : null,
            $data['properties'] ?? [],
        );

        return response()->json(['success' => true]);
    }

    public function event(Request $request, string $appId, string $id): JsonResponse
    {
        $subscription = $this->subscription($appId, $id);
        $data = $request->validate([
            'message_id' => ['required', 'uuid'],
            'event' => ['required', Rule::in(['received', 'clicked'])],
            'action_id' => ['nullable', 'string', 'max:64'],
        ]);

        Events::record($subscription, $data['message_id'], $data['event'], $data['action_id'] ?? null);

        // A click opens the app: it is a session too.
        if ($data['event'] === 'clicked') {
            $subscription->forceFill(['last_active_at' => now()])->save();
        }

        return response()->json(['success' => true]);
    }

    /** @return array<string, list<mixed>> */
    protected function deviceRules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'environment' => ['nullable', Rule::in(['sandbox', 'production'])],
            'language' => ['nullable', 'string', 'max:8'],
            'timezone' => ['nullable', 'timezone'],
            'country' => ['nullable', 'string', 'size:2'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'device_model' => ['nullable', 'string', 'max:64'],
            'device_os' => ['nullable', 'string', 'max:32'],
            'sdk' => ['nullable', 'string', 'max:32'],
        ];
    }

    /** @param array<string, mixed> $data */
    protected function applyDevice(Subscription $subscription, array $data): void
    {
        foreach (['enabled', 'environment', 'app_version', 'device_model', 'device_os', 'sdk'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $subscription->{$field} = $data[$field];
            }
        }
        $subscription->last_active_at ??= now();
    }

    /** @param array<string, mixed> $data */
    protected function applyPerson(Subscriber $subscriber, array $data): void
    {
        if (filled($data['language'] ?? null)) {
            $subscriber->language = strtolower(substr($data['language'], 0, 2));
        }
        if (filled($data['timezone'] ?? null)) {
            $subscriber->timezone = $data['timezone'];
        }
        if (filled($data['country'] ?? null)) {
            $subscriber->country = strtoupper($data['country']);
        }
        if (! empty($data['tags'])) {
            $subscriber->mergeTags($data['tags']);
        }
        $subscriber->last_active_at = now();
        $subscriber->save();
    }

    /** Web push subscriptions are stored as canonical JSON (same browser = same token). */
    protected function normalizeToken(string $type, string $token): string
    {
        if ($type === 'email') {
            $email = self::email($token);
            abort_unless($email, 422, 'Invalid email address.');

            return $email;
        }
        if ($type !== 'web') {
            return trim($token);
        }

        $data = json_decode($token, true);
        abort_unless(isset($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth']), 422, 'Invalid web push subscription.');

        return json_encode(['endpoint' => $data['endpoint'], 'keys' => ['p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth']]], JSON_UNESCAPED_SLASHES);
    }

    protected function project(string $appId): Project
    {
        $project = Project::query()->where('app_id', $appId)->first();
        abort_unless($project, 404, 'Unknown app_id.');

        return $project;
    }

    protected function subscription(string $appId, string $id): Subscription
    {
        $subscription = Subscription::query()->with('subscriber')->whereKey($id)
            ->whereHas('project', fn ($q) => $q->where('app_id', $appId))->first();
        abort_unless($subscription, 404, 'Unknown subscription.');

        return $subscription;
    }

    /** @return array<string, mixed> */
    /** Adds an email address to the person of this device (OneSignal addEmail). */
    public function addEmail(Request $request, string $appId, string $id): JsonResponse
    {
        $device = $this->subscription($appId, $id);
        $request->validate(['email' => ['required', 'string', 'max:255']]);
        $email = self::email((string) $request->input('email'));
        abort_unless($email, 422, 'Invalid email address.');

        $subscription = Subscription::query()->firstOrNew(['project_id' => $device->project_id, 'token_hash' => hash('sha256', $email)]);
        $created = ! $subscription->exists;
        $subscription->fill(['subscriber_id' => $device->subscriber_id, 'type' => 'email', 'token' => $email, 'invalid_at' => null, 'sdk' => $device->sdk]);
        // Someone who unsubscribed stays unsubscribed (their choice wins).
        if ($created) {
            $subscription->enabled = true;
        }
        $subscription->save();

        if ($created) {
            Webhooks::dispatch($device->project_id, 'subscription.created', $this->webhookData($subscription->fresh('subscriber')));
        }

        return response()->json(['id' => $subscription->id, 'email' => $email, 'enabled' => $subscription->enabled], $created ? 201 : 200);
    }

    /** Removes an email address of the person (OneSignal removeEmail). */
    public function removeEmail(Request $request, string $appId, string $id): JsonResponse
    {
        $device = $this->subscription($appId, $id);
        $email = self::email((string) $request->input('email'));

        Subscription::query()
            ->where('project_id', $device->project_id)
            ->where('subscriber_id', $device->subscriber_id)
            ->where('type', 'email')
            ->when($email, fn ($q) => $q->where('token_hash', hash('sha256', $email)))
            ->delete();

        return response()->json(['success' => true]);
    }

    /** Lowercase address, or null if it is not one. */
    public static function email(string $value): ?string
    {
        $email = mb_strtolower(trim($value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 255 ? $email : null;
    }

    /** @return array<string, mixed> */
    protected function webhookData(Subscription $subscription, ?string $reason = null): array
    {
        return array_filter([
            'subscription_id' => $subscription->id,
            'subscriber_id' => $subscription->subscriber_id,
            'external_id' => $subscription->subscriber?->external_id,
            'platform' => $subscription->type,
            'language' => $subscription->subscriber?->language,
            'device_model' => $subscription->device_model,
            'app_version' => $subscription->app_version,
            'reason' => $reason,
        ], fn ($value) => $value !== null);
    }

    /** @return array<string, mixed> */
    protected function present(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'subscriber_id' => $subscription->subscriber_id,
            'external_id' => $subscription->subscriber?->external_id,
            'enabled' => $subscription->enabled,
            'tags' => (object) ($subscription->subscriber?->tags ?? []),
        ];
    }
}
