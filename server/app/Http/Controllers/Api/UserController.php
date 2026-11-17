<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Sdk\SubscriptionController;
use App\Journeys\JourneyRunner;
use App\Models\Project;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Sms\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * People and their devices, OneSignal "Users" API format:
 * /apps/{app_id}/users and /apps/{app_id}/users/by/external_id/{id}.
 */
class UserController extends Controller
{
    /** OneSignal subscription types and ours. */
    public const TYPES = ['iOSPush' => 'ios', 'AndroidPush' => 'android', 'ChromePush' => 'web', 'SafariPush' => 'web', 'FirefoxPush' => 'web', 'SafariLegacyPush' => 'web', 'Email' => 'email', 'SMS' => 'sms', 'WhatsApp' => 'whatsapp'];

    /** Creates (or updates) a person with devices: used to import an audience. */
    public function store(Request $request, string $appId): JsonResponse
    {
        $project = $this->project($request);
        $data = $request->validate([
            'identity.external_id' => ['nullable', 'string', 'max:255'],
            'properties' => ['nullable', 'array'],
            'properties.tags' => ['nullable', 'array', 'max:'.Subscriber::MAX_TAGS],
            'properties.language' => ['nullable', 'string', 'max:8'],
            'properties.timezone_id' => ['nullable', 'timezone'],
            'properties.country' => ['nullable', 'string', 'size:2'],
            'properties.lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:properties.long'],
            'properties.long' => ['nullable', 'numeric', 'between:-180,180', 'required_with:properties.lat'],
            'subscriptions' => ['nullable', 'array', 'max:20'],
            'subscriptions.*.type' => ['required', Rule::in(array_keys(self::TYPES))],
            'subscriptions.*.token' => ['required', 'string', 'max:4000'],
            'subscriptions.*.enabled' => ['nullable', 'boolean'],
            'subscriptions.*.app_version' => ['nullable', 'string', 'max:32'],
            'subscriptions.*.device_model' => ['nullable', 'string', 'max:64'],
            'subscriptions.*.device_os' => ['nullable', 'string', 'max:32'],
            'subscriptions.*.test_type' => ['nullable', 'integer'],
        ]);

        $subscriber = DB::transaction(function () use ($project, $data) {
            $externalId = $data['identity']['external_id'] ?? null;
            $subscriber = $externalId
                ? Subscriber::query()->firstOrNew(['project_id' => $project->id, 'external_id' => $externalId])
                : new Subscriber(['project_id' => $project->id]);

            $tagsBefore = $subscriber->getOriginal('tags');
            $this->applyProperties($subscriber, $data['properties'] ?? []);
            $subscriber->save();
            app(JourneyRunner::class)->tagsChanged($subscriber, $tagsBefore);

            foreach ($data['subscriptions'] ?? [] as $item) {
                $type = self::TYPES[$item['type']];
                if ($type === 'email') {
                    $item['token'] = SubscriptionController::email($item['token'])
                        ?? throw ValidationException::withMessages(['subscriptions' => 'Invalid email address.']);
                }
                if ($type === 'sms' || $type === 'whatsapp') {
                    $item['token'] = Phone::normalize($item['token'])
                        ?? throw ValidationException::withMessages(['subscriptions' => 'Invalid phone number: use the international format (+2250700000000).']);
                }
                $subscription = Subscription::query()->firstOrNew(array_filter(['project_id' => $project->id, 'type' => in_array($type, Subscription::PUSH_TYPES, true) ? null : $type, 'token_hash' => hash('sha256', $item['token'])]));
                $subscription->fill([
                    'subscriber_id' => $subscriber->id,
                    'type' => $type,
                    'token' => $item['token'],
                    'enabled' => $item['enabled'] ?? true,
                    'invalid_at' => null,
                    // OneSignal test_type 1 = development build (APNs sandbox).
                    'environment' => $type === 'ios' ? (($item['test_type'] ?? null) === 1 ? 'sandbox' : 'production') : null,
                    'app_version' => $item['app_version'] ?? null,
                    'device_model' => $item['device_model'] ?? null,
                    'device_os' => $item['device_os'] ?? null,
                    'sdk' => 'import',
                ]);
                if (! $subscription->enabled && $subscription->status_reason === null) {
                    $subscription->setStatus('disabled_by_api');
                }
                $subscription->save();
            }
            $this->locate($subscriber, $data['properties'] ?? []);

            return $subscriber;
        });

        return response()->json($this->present($subscriber->load('subscriptions')), 201);
    }

    public function show(Request $request, string $appId, string $externalId): JsonResponse
    {
        return response()->json($this->present($this->subscriber($request, $externalId)->load('subscriptions')));
    }

    /** {"properties": {"tags": {...}, "language": "fr", "timezone_id": "Africa/Abidjan"}} */
    public function update(Request $request, string $appId, string $externalId): JsonResponse
    {
        $subscriber = $this->subscriber($request, $externalId);
        $data = $request->validate([
            'properties' => ['required', 'array'],
            'properties.tags' => ['nullable', 'array', 'max:'.Subscriber::MAX_TAGS],
            'properties.language' => ['nullable', 'string', 'max:8'],
            'properties.timezone_id' => ['nullable', 'timezone'],
            'properties.country' => ['nullable', 'string', 'size:2'],
            'properties.lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:properties.long'],
            'properties.long' => ['nullable', 'numeric', 'between:-180,180', 'required_with:properties.lat'],
        ]);

        $tagsBefore = $subscriber->tags;
        $this->applyProperties($subscriber, $data['properties']);
        $subscriber->save();
        $this->locate($subscriber, $data['properties']);
        app(JourneyRunner::class)->tagsChanged($subscriber, $tagsBefore);

        return response()->json(['properties' => $this->properties($subscriber)]);
    }

    public function destroy(Request $request, string $appId, string $externalId): JsonResponse
    {
        $this->subscriber($request, $externalId)->delete();

        return response()->json(['success' => true]);
    }

    /** @param array<string, mixed> $properties */
    protected function applyProperties(Subscriber $subscriber, array $properties): void
    {
        if (isset($properties['tags'])) {
            $subscriber->mergeTags($properties['tags']);
        }
        if (filled($properties['language'] ?? null)) {
            $subscriber->language = strtolower(substr($properties['language'], 0, 2));
        }
        if (filled($properties['timezone_id'] ?? null)) {
            $subscriber->timezone = $properties['timezone_id'];
        }
        if (filled($properties['country'] ?? null)) {
            $subscriber->country = strtoupper($properties['country']);
        }
    }

    /**
     * Position sent by the server of the app (OneSignal "lat"/"long"): given
     * to the push devices of the person, unless the project turned it off.
     *
     * @param  array<string, mixed>  $properties
     */
    protected function locate(Subscriber $subscriber, array $properties): void
    {
        if (! isset($properties['lat'], $properties['long']) || ! $subscriber->project->collectsLocation()) {
            return;
        }
        $subscriber->subscriptions()->whereIn('type', Subscription::PUSH_TYPES)->get()
            ->each(function (Subscription $subscription) use ($properties) {
                $subscription->locate((float) $properties['lat'], (float) $properties['long']);
                $subscription->save();
            });
    }

    protected function project(Request $request): Project
    {
        return $request->attributes->get('project');
    }

    protected function subscriber(Request $request, string $externalId): Subscriber
    {
        $subscriber = $this->project($request)->subscribers()->where('external_id', $externalId)->first();
        abort_unless($subscriber, 404, 'User not found');

        return $subscriber;
    }

    /** @return array<string, mixed> */
    protected function properties(Subscriber $subscriber): array
    {
        return array_filter([
            'tags' => (object) ($subscriber->tags ?? []),
            'language' => $subscriber->language,
            'timezone_id' => $subscriber->timezone,
            'country' => $subscriber->country,
            'last_active' => $subscriber->last_active_at?->getTimestamp(),
            ...$this->lastLocation($subscriber),
        ], fn ($value) => $value !== null);
    }

    /** @return array{lat?: float, long?: float} */
    protected function lastLocation(Subscriber $subscriber): array
    {
        $device = $subscriber->subscriptions()->whereNotNull('latitude')->latest('located_at')->first(['latitude', 'longitude']);

        return $device ? ['lat' => $device->latitude, 'long' => $device->longitude] : [];
    }

    /** @return array<string, mixed> */
    protected function present(Subscriber $subscriber): array
    {
        $types = array_flip(['iOSPush' => 'ios', 'AndroidPush' => 'android', 'ChromePush' => 'web', 'Email' => 'email', 'SMS' => 'sms', 'WhatsApp' => 'whatsapp']);

        return [
            'identity' => array_filter(['onesignal_id' => $subscriber->id, 'external_id' => $subscriber->external_id]),
            'properties' => $this->properties($subscriber),
            'subscriptions' => $subscriber->subscriptions->map(fn (Subscription $s) => array_filter([
                'id' => $s->id,
                'type' => $types[$s->type] ?? $s->type,
                'token' => $s->token,
                'enabled' => $s->enabled && $s->invalid_at === null,
                'app_version' => $s->app_version,
                'device_model' => $s->device_model,
                'device_os' => $s->device_os,
                'sdk' => $s->sdk,
                'session_count' => $s->sessions,
                'status_reason' => $s->status_reason,
                'status_detail' => $s->status_detail,
            ], fn ($value) => $value !== null))->values()->all(),
        ];
    }
}
