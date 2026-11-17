<?php

namespace App\Http\Controllers\Api;

use App\Audience\Conversions;
use App\Http\Controllers\Controller;
use App\Journeys\JourneyRunner;
use App\Models\Integration;
use App\Models\Project;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Source compatible with Segment and RudderStack: the Webhooks destination
 * of those tools, or their HTTP API (identify, track, page, screen, batch),
 * authenticated with the write key of the integration.
 *
 * identify → user created or updated by userId (traits become tags)
 * track    → custom event of the user (counted in conversions and journeys)
 */
class SegmentSourceController extends Controller
{
    public const MAX_EVENTS = 500;

    public function __invoke(Request $request, string $appId, Conversions $conversions, ?string $path = null): JsonResponse
    {
        $project = Project::query()->where('app_id', $appId)->first();
        $key = $this->key($request);
        $integration = $project && $key ? $project->integrations()->where('kind', 'segment')->where('enabled', true)->get()
            ->first(fn (Integration $i) => hash_equals((string) $i->secret, $key)) : null;
        if (! $integration) {
            return response()->json(['success' => false, 'error' => 'Invalid write key.'], 401);
        }

        $body = $request->json()->all();
        $type = basename((string) $path) ?: null;
        $events = isset($body['batch']) && is_array($body['batch']) ? $body['batch'] : [$body + ($type && $type !== 'batch' ? ['type' => $type] : [])];
        if (count($events) > self::MAX_EVENTS) {
            return response()->json(['success' => false, 'error' => 'At most '.self::MAX_EVENTS.' events per request.'], 400);
        }

        $counts = ['identify' => 0, 'track' => 0, 'skipped' => 0];
        foreach ($events as $event) {
            try {
                $done = is_array($event) ? $this->handle($project, $integration, $event, $conversions) : null;
            } catch (Throwable $e) {
                report($e);
                $done = null;
            }
            $counts[$done ?? 'skipped']++;
        }

        $integration->forceFill([
            'last_run_at' => now(),
            'last_success_at' => now(),
            'last_error' => null,
            'last_result' => ['identify' => $counts['identify'], 'track' => $counts['track'], 'skipped' => $counts['skipped']],
        ])->save();

        return response()->json(['success' => true] + $counts);
    }

    /** @return 'identify'|'track'|null */
    protected function handle(Project $project, Integration $integration, array $event, Conversions $conversions): ?string
    {
        $type = strtolower((string) ($event['type'] ?? ''));
        $userId = isset($event['userId']) && is_scalar($event['userId']) ? mb_substr((string) $event['userId'], 0, 255) : null;
        if (! filled($userId)) {
            return null; // anonymous visitors have no user in Carillon
        }
        $at = $this->time($event);

        if ($type === 'identify') {
            $subscriber = $project->subscribers()->where('external_id', $userId)->first();
            if (! $subscriber && ! $integration->setting('create_users', true)) {
                return null;
            }
            $subscriber ??= new Subscriber(['project_id' => $project->id, 'external_id' => $userId]);
            $tagsBefore = $subscriber->tags;
            $this->applyTraits($subscriber, $integration, (array) ($event['traits'] ?? $event['context']['traits'] ?? []), (array) ($event['context'] ?? []));
            $subscriber->save();
            app(JourneyRunner::class)->tagsChanged($subscriber, $tagsBefore);

            return 'identify';
        }

        if (in_array($type, ['track', 'page', 'screen'], true)) {
            $subscriber = $project->subscribers()->where('external_id', $userId)->first();
            if (! $subscriber) {
                return null;
            }
            $name = $type === 'track' ? (string) ($event['event'] ?? '') : $type.(filled($event['name'] ?? null) ? ': '.$event['name'] : '');
            if (! filled($name)) {
                return null;
            }
            $properties = array_filter((array) ($event['properties'] ?? []), fn ($v) => is_scalar($v) || $v === null);
            $conversions->record($project, $subscriber, mb_substr($name, 0, 128), null, $properties, $at);

            return 'track';
        }

        return null;
    }

    /** Scalar traits as tags; locale and timezone of the context as properties. */
    protected function applyTraits(Subscriber $subscriber, Integration $integration, array $traits, array $context): void
    {
        $skip = array_filter(array_map('trim', explode(',', (string) $integration->setting('skip_traits', ''))));
        $tags = [];
        foreach (Arr::dot($traits) as $key => $value) {
            if (in_array($key, $skip, true) || in_array($key, ['id', 'userId'], true) || (! is_scalar($value) && $value !== null)) {
                continue;
            }
            $tags[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }
        $subscriber->mergeTags($tags);

        $locale = $context['locale'] ?? null;
        if (is_string($locale) && preg_match('/^([a-z]{2})/i', $locale, $m)) {
            $subscriber->language = strtolower($m[1]);
        }
        $timezone = $context['timezone'] ?? null;
        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            $subscriber->timezone = $timezone;
        }
        $country = $traits['address']['country'] ?? $context['location']['country'] ?? null;
        if (is_string($country) && strlen($country) === 2) {
            $subscriber->country = strtoupper($country);
        }
    }

    protected function key(Request $request): ?string
    {
        // Segment: Basic auth with the write key as user name.
        $key = $request->getUser() ?: $request->bearerToken() ?: $request->header('X-Write-Key') ?: $request->query('key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    protected function time(array $event): ?Carbon
    {
        foreach (['timestamp', 'originalTimestamp', 'sentAt'] as $field) {
            if (is_string($event[$field] ?? null)) {
                try {
                    $at = Carbon::parse($event[$field]);

                    return $at->isFuture() ? now() : $at;
                } catch (Throwable) {
                }
            }
        }

        return null;
    }
}
