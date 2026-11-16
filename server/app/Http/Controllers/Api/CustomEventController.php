<?php

namespace App\Http\Controllers\Api;

use App\Audience\Conversions;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Custom events sent by a server, in the format of OneSignal:
 * POST /apps/{app_id}/custom_events {"events": [{"name", "external_id", "properties", "timestamp"}]}.
 */
class CustomEventController extends Controller
{
    public function store(Request $request, string $appId, Conversions $conversions): JsonResponse
    {
        /** @var Project $project */
        $project = $request->attributes->get('project');

        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:1000'],
            'events.*.name' => ['required', 'string', 'max:128'],
            'events.*.external_id' => ['nullable', 'string', 'max:255'],
            'events.*.onesignal_id' => ['nullable', 'string', 'max:64'],
            'events.*.subscription_id' => ['nullable', 'string', 'max:64'],
            'events.*.value' => ['nullable', 'numeric'],
            'events.*.properties' => ['nullable', 'array'],
            'events.*.timestamp' => ['nullable', 'date'],
        ]);

        $recorded = 0;
        $unknown = 0;
        foreach ($data['events'] as $event) {
            $subscriber = $this->person($project, $event);
            if (! $subscriber && (filled($event['external_id'] ?? null) || filled($event['onesignal_id'] ?? null) || filled($event['subscription_id'] ?? null))) {
                $unknown++;

                continue;
            }

            $conversions->record(
                $project,
                $subscriber,
                $event['name'],
                isset($event['value']) ? (float) $event['value'] : null,
                $event['properties'] ?? [],
                isset($event['timestamp']) ? Carbon::parse($event['timestamp']) : null,
            );
            $recorded++;
        }

        return response()->json(array_filter(['success' => true, 'recorded' => $recorded, 'unknown_users' => $unknown ?: null]));
    }

    /** The person of an event: by external id, Carillon id or OneSignal id. */
    protected function person(Project $project, array $event): ?Subscriber
    {
        if (filled($event['external_id'] ?? null)) {
            return $project->subscribers()->where('external_id', $event['external_id'])->first();
        }

        $id = $event['onesignal_id'] ?? $event['subscription_id'] ?? null;
        if (! filled($id)) {
            return null;
        }

        return $project->subscribers()
            ->where(fn ($q) => $q
                ->when(Str::isUuid($id), fn ($q) => $q->whereKey($id)->orWhereHas('subscriptions', fn ($s) => $s->whereKey($id)))
                ->orWhereHas('subscriptions', fn ($s) => $s->where('onesignal_id', $id)))
            ->first();
    }
}
