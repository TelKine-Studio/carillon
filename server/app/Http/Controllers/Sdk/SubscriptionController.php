<?php

namespace App\Http\Controllers\Sdk;

use App\Audience\Conversions;
use App\Audience\Events;
use App\Audience\Identity;
use App\Audience\IdentityChecks;
use App\Audience\Presence;
use App\Http\Controllers\Controller;
use App\Journeys\JourneyRunner;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Push\WebPushEndpoint;
use App\Sms\Phone;
use App\Webhooks\Webhooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
            // An iPhone without the notification permission has no APNs
            // token, but can still show Live Activities.
            'token' => [Rule::requiredIf($request->input('type') !== 'ios'), 'nullable', 'string', 'max:4000'],
            ...$this->deviceRules(),
            'external_id' => ['nullable', 'string', 'max:255'],
            'identity_hash' => ['nullable', 'string', 'max:128'],
            'tags' => ['nullable', 'array', 'max:'.Subscriber::MAX_TAGS],
        ]);

        $token = filled($data['token'] ?? null) ? $this->normalizeToken($data['type'], $data['token']) : null;
        $contact = ! in_array($data['type'], Subscription::PUSH_TYPES, true);
        if ($contact) {
            $this->throttleContacts($request);
        }
        // Without a valid identity hash the device still registers (it must
        // keep receiving notifications), but anonymously.
        if (filled($data['external_id'] ?? null) && ! $this->verifyIdentity($project, $data['external_id'], $request->input('identity_hash'), strict: false)) {
            unset($data['external_id']);
        }

        $created = false;
        $subscription = DB::transaction(function () use ($project, $data, $token, $contact, &$created) {
            // A phone number may be both an SMS and a WhatsApp subscription.
            $subscription = $token === null ? null : Subscription::query()
                ->where('project_id', $project->id)
                ->where('token_hash', hash('sha256', $token))
                ->when(! in_array($data['type'], Subscription::PUSH_TYPES, true), fn ($q) => $q->where('type', $data['type']))
                ->first();

            if (! $subscription) {
                $created = true;
                $subscriber = $this->identity->newSubscriber($project, ['last_active_at' => now()]);
                $subscription = new Subscription(['project_id' => $project->id, 'subscriber_id' => $subscriber->id, 'type' => $data['type']]);
            } elseif ($contact) {
                // An email address or a phone number is not a secret: whoever
                // knows it must not unsubscribe, re-subscribe or read the person.
                abort_unless($this->claimable($subscription, $data['external_id'] ?? null), 409, 'This contact is already registered.');
                $subscription->save();

                return $subscription;
            }

            $subscription->fill(['token' => $token, 'invalid_at' => null]);
            $this->applyDevice($subscription, $data);
            $subscription->save();

            return $subscription;
        });

        if (! $created && $contact) {
            return response()->json(['id' => $subscription->id, 'enabled' => $subscription->enabled]);
        }
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
            // Email addresses and phone numbers added by an app are not proven:
            // a journey must not text or mail numbers chosen by anyone.
            if (! $contact) {
                $journeys->trigger($project, 'new_device', $subscriber);
            }
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
            'type' => ['nullable', Rule::in(Subscription::PUSH_TYPES)],
        ]);

        // A Huawei or Fire device switches to Push Kit or ADM (setPushToken).
        if (filled($data['type'] ?? null) && in_array($subscription->type, Subscription::PUSH_TYPES, true)) {
            $subscription->type = $data['type'];
        }
        if (filled($data['token'] ?? null)) {
            $token = $this->normalizeToken($subscription->type, $data['token']);
            if (! in_array($subscription->type, Subscription::PUSH_TYPES, true)) {
                $other = Subscription::query()->where('project_id', $subscription->project_id)->where('type', $subscription->type)
                    ->where('token_hash', hash('sha256', $token))->whereKeyNot($subscription->id)->with('subscriber')->first();
                abort_if($other && ! $this->claimable($other, $subscription->subscriber?->external_id, $subscription->subscriber_id), 409, 'This contact is already registered.');
            }
            $subscription->token = $token;
            $subscription->invalid_at = null;

            // The token now belongs to this device (an iPhone registered
            // without permission gets it later; a reinstalled app may get
            // its old one back): the other record of it is dead.
            Subscription::query()->where('project_id', $subscription->project_id)->where('type', $subscription->type)
                ->where('token_hash', hash('sha256', $subscription->token))->whereKeyNot($subscription->id)
                ->update(['token' => null, 'token_hash' => null, 'invalid_at' => now(), 'updated_at' => now(), 'unsubscribed_at' => now(),
                    'status_reason' => 'token_reused', 'status_detail' => null, 'status_changed_at' => now()]);
        }
        $wasEnabled = $subscription->enabled;
        $this->applyDevice($subscription, $data);

        if ($request->boolean('session')) {
            $subscription->sessions++;
            $subscription->last_active_at = now();
        }
        // The SDK syncs when the app starts or comes back to the foreground:
        // "online now" right away, without waiting for the first heartbeat.
        $subscription->seen_at = now();
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
        $data = $request->validate(['external_id' => ['required', 'string', 'max:255'], 'identity_hash' => ['nullable', 'string', 'max:128']]);
        $this->verifyIdentity($subscription->project, $data['external_id'], $data['identity_hash'] ?? null);

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
            // Notification permission on the device: tells why it is disabled.
            'permission' => ['nullable', Rule::in(['granted', 'denied', 'not_determined', 'provisional', 'ephemeral'])],
            'environment' => ['nullable', Rule::in(['sandbox', 'production'])],
            'language' => ['nullable', 'string', 'max:8'],
            'timezone' => ['nullable', 'timezone'],
            'country' => ['nullable', 'string', 'size:2'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'device_model' => ['nullable', 'string', 'max:64'],
            'device_os' => ['nullable', 'string', 'max:32'],
            'sdk' => ['nullable', 'string', 'max:32'],
            // Last known position, sent only when the app already has the
            // location permission (same names as OneSignal).
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:long'],
            'long' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            // false: the app (or the person) stopped sharing it.
            'location_shared' => ['nullable', 'boolean'],
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
        // Talking to this endpoint means the Carillon SDK runs on the device:
        // the nightly OneSignal sync must leave it alone from now on.
        if (! $subscription->sdk || $subscription->sdk === Subscription::ONESIGNAL_SDK) {
            $subscription->sdk = 'carillon';
        }
        $subscription->last_active_at ??= now();
        $this->applyLocation($subscription, $data);

        if (array_key_exists('enabled', $data) && $data['enabled'] !== null && ! filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN)) {
            $reason = match ($data['permission'] ?? null) {
                'denied' => 'permission_denied',
                'not_determined' => 'permission_not_asked',
                'granted', 'provisional', 'ephemeral' => 'opted_out',
                default => 'disabled_on_device',
            };
            if ($subscription->status_reason !== $reason) {
                $subscription->setStatus($reason);
            }
        }
    }

    /** @param array<string, mixed> $data */
    protected function applyLocation(Subscription $subscription, array $data): void
    {
        $project = $subscription->project;
        if (! $project->collectsLocation() || ($data['location_shared'] ?? null) === false) {
            if ($subscription->latitude !== null) {
                $subscription->locate(null, null);
            }

            return;
        }
        if (isset($data['lat'], $data['long'])) {
            $subscription->locate((float) $data['lat'], (float) $data['long'], isset($data['location_accuracy']) ? (float) $data['location_accuracy'] : null);
        }
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
        if ($type === 'sms' || $type === 'whatsapp') {
            $phone = Phone::normalize($token);
            abort_unless($phone, 422, 'Invalid phone number: use the international format (+2250700000000).');

            return $phone;
        }
        if ($type !== 'web') {
            return trim($token);
        }

        $data = json_decode($token, true);
        abort_unless(isset($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth']) && is_string($data['endpoint']), 422, 'Invalid web push subscription.');
        abort_unless(WebPushEndpoint::allowed($data['endpoint']), 422, 'The endpoint is not the push service of a browser.');

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
        // PostgreSQL refuses to compare a UUID column with any other text.
        abort_unless(Str::isUuid($id), 404, 'Unknown subscription.');
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

        $this->throttleContacts($request);

        $subscription = Subscription::query()->with('subscriber')->firstOrNew(['project_id' => $device->project_id, 'token_hash' => hash('sha256', $email)]);
        $created = ! $subscription->exists;
        abort_unless($created || $this->claimable($subscription, $device->subscriber?->external_id, $device->subscriber_id), 409, 'This email address belongs to another user.');
        $subscription->fill(['subscriber_id' => $device->subscriber_id, 'type' => 'email', 'token' => $email, 'sdk' => $device->sdk]);
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

    /** The app is open (sent every minute in the foreground): "online now". */
    public function heartbeat(string $appId, string $id): JsonResponse
    {
        $subscription = $this->subscription($appId, $id);
        Presence::seen($subscription);

        return response()->json(['success' => true]);
    }

    /** Adds a phone number to the person of this device (OneSignal addSms). */
    public function addSms(Request $request, string $appId, string $id): JsonResponse
    {
        return $this->addPhone($request, $appId, $id, 'sms');
    }

    /** Adds a WhatsApp number (the person agreed to receive messages there). */
    public function addWhatsApp(Request $request, string $appId, string $id): JsonResponse
    {
        return $this->addPhone($request, $appId, $id, 'whatsapp');
    }

    public function removeSms(Request $request, string $appId, string $id): JsonResponse
    {
        return $this->removePhone($request, $appId, $id, 'sms');
    }

    public function removeWhatsApp(Request $request, string $appId, string $id): JsonResponse
    {
        return $this->removePhone($request, $appId, $id, 'whatsapp');
    }

    protected function addPhone(Request $request, string $appId, string $id, string $type): JsonResponse
    {
        $device = $this->subscription($appId, $id);
        $request->validate(['phone_number' => ['required', 'string', 'max:32']]);
        $phone = Phone::normalize((string) $request->input('phone_number'));
        abort_unless($phone, 422, 'Invalid phone number: use the international format (+2250700000000).');

        $this->throttleContacts($request);

        $subscription = Subscription::query()->with('subscriber')->firstOrNew(['project_id' => $device->project_id, 'type' => $type, 'token_hash' => hash('sha256', $phone)]);
        $created = ! $subscription->exists;
        abort_unless($created || $this->claimable($subscription, $device->subscriber?->external_id, $device->subscriber_id), 409, 'This phone number belongs to another user.');
        $subscription->fill(['subscriber_id' => $device->subscriber_id, 'type' => $type, 'token' => $phone, 'sdk' => $device->sdk]);
        if ($created) {
            $subscription->enabled = true;
        }
        $subscription->save();

        if ($created) {
            Webhooks::dispatch($device->project_id, 'subscription.created', $this->webhookData($subscription->fresh('subscriber')));
        }

        return response()->json(['id' => $subscription->id, 'phone_number' => $phone, 'enabled' => $subscription->enabled], $created ? 201 : 200);
    }

    /** Removes a phone number of the person (OneSignal removeSms). */
    protected function removePhone(Request $request, string $appId, string $id, string $type): JsonResponse
    {
        $device = $this->subscription($appId, $id);
        $phone = Phone::normalize((string) $request->input('phone_number'));

        Subscription::query()
            ->where('project_id', $device->project_id)
            ->where('subscriber_id', $device->subscriber_id)
            ->where('type', $type)
            ->when($phone, fn ($q) => $q->where('token_hash', hash('sha256', $phone)))
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Whether an existing email or phone contact may be taken by this
     * request: it belongs to nobody known (anonymous person), to the same
     * person, or to the same external id (the user logged in on a new device).
     */
    protected function claimable(Subscription $contact, ?string $externalId, ?string $subscriberId = null): bool
    {
        $owner = $contact->subscriber;
        if (! $owner || $owner->external_id === null) {
            return true;
        }

        return ($subscriberId !== null && $owner->id === $subscriberId)
            || (filled($externalId) && hash_equals($owner->external_id, (string) $externalId));
    }

    /** Email addresses and phone numbers added by apps: 30 per hour per address IP. */
    protected function throttleContacts(Request $request): void
    {
        $key = 'sdk-contacts:'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 30), 429, 'Too many contacts added: try again later.');
        RateLimiter::hit($key, 3600);
    }

    /**
     * Identity verification (setting of the project): the server of the app
     * signs the external id with the identity secret, HMAC-SHA256 in hex, so
     * that a device cannot log in as someone else.
     */
    protected function verifyIdentity(Project $project, string $externalId, mixed $hash, bool $strict = true): bool
    {
        $secret = $project->identitySecret();
        if ($secret === null) {
            return true;
        }

        // Counted verification on or off: the project sees when its installed
        // apps send the hash before turning the verification on.
        $result = ! is_string($hash) || $hash === '' ? 'missing'
            : (hash_equals(hash_hmac('sha256', $externalId, $secret), strtolower($hash)) ? 'valid' : 'invalid');
        IdentityChecks::count($project, $result);

        $allowed = ! $project->verifiesIdentity() || $result === 'valid';
        abort_if($strict && ! $allowed, 401, 'Identity verification failed: send identity_hash = HMAC-SHA256(external_id, identity secret).');

        return $allowed;
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
            // Whether the SDK may send the position (setting of the project).
            'location' => $subscription->project->collectsLocation(),
        ];
    }
}
