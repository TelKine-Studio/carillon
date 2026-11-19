<?php

namespace App\LiveActivities;

use App\Audience\Audience;
use App\Models\LiveActivity;
use App\Models\LiveActivityChannel;
use App\Models\LiveActivityStartToken;
use App\Models\Message;
use App\Models\Project;
use App\Models\PushCredential;
use App\Models\Subscription;
use App\Push\ApnsSender;
use App\Push\FcmSender;
use App\Push\Result;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Live Activities, OneSignal style, on iOS and Android:
 * - start: by push, to the devices targeted. iOS needs the push-to-start
 *   token of the device for the type (iOS 17.2+); Android (Carillon SDK
 *   0.3+) shows them as Live Updates: an ongoing notification with
 *   progress, promoted on Android 16, refreshed by FCM data messages;
 * - update and end: on iOS 18+, through the broadcast channel of the
 *   activity (created at the start), with no token from the app; else to
 *   every device showing the activity, with its update token. The token
 *   only exists once the app received it from iOS and the SDK sent it:
 *   what comes before is kept and sent when it arrives;
 * - an activity nobody ended is ended by Carillon at end_at.
 */
class LiveActivities
{
    /** Apple ends an activity after 8 hours anyway. */
    public const MAX_DURATION = 8 * 3600;

    /** Ends sent at most per activity (Apple may accept one that never arrives). */
    public const MAX_ENDS = 3;

    /** Minutes between two ends while the app did not confirm. */
    public const END_RETRY = 5;

    /** Updates sent per activity: past that, a loop in a backend is suspected. */
    public const MAX_UPDATES = 500;

    /** First iOS able to start an activity on a broadcast channel. */
    public const CHANNEL_IOS = 18;

    /** Minutes without trying channels again after Apple refused one. */
    public const CHANNEL_PAUSE = 60;

    public function __construct(protected ApnsSender $apns, protected FcmSender $fcm, protected Audience $audience) {}

    /**
     * Starts the activity on the targeted devices able to show it: iPhones
     * with a push-to-start token for this type, Android apps on the
     * Carillon SDK 0.3+. A device already showing this activity_id is left
     * alone: calling start twice is harmless.
     *
     * @param  array<string, mixed>  $data
     * @return array{recipients: int, already: int, targeted: int, skipped: array{no_start_token: int, unsupported: int}, errors: list<string>}
     */
    public function start(Project $project, string $type, array $data): array
    {
        $data = Template::start($data);
        [$apns, $fcm] = $this->credentials($project);
        $targeted = $this->audience->query($project, [...$this->targeting($data), 'platforms' => Subscription::PUSH_TYPES, 'any_permission' => true])
            ->leftJoin('live_activity_start_tokens', function ($join) use ($type) {
                $join->on('live_activity_start_tokens.subscription_id', '=', 'subscriptions.id')
                    ->where('live_activity_start_tokens.activity_type', '=', $type);
            })
            ->addSelect('live_activity_start_tokens.token as start_token', 'live_activity_start_tokens.id as start_token_id', 'subscribers.language as person_language')
            ->limit(10000)
            ->get();

        // Why the others are left out, for the caller: an iPhone that has not
        // sent the push-to-start token of this type (yet: iOS < 17.2, app
        // not opened since the SDK 0.3, widget without this type), or a
        // device that cannot show it (other platform, old SDK, Android
        // without notification permission, provider not configured).
        $devices = $targeted->filter(fn (Subscription $device) => $device->type === 'ios'
            ? $apns && $device->start_token
            : $fcm && $device->supportsLiveUpdates() && $device->enabled && filled($device->token));
        $noStartToken = $targeted->filter(fn (Subscription $device) => $device->type === 'ios' && $apns && ! $device->start_token)->count();
        $leftOut = [
            'no_start_token' => $noStartToken,
            'unsupported' => $targeted->count() - $devices->count() - $noStartToken,
        ];

        $activityId = (string) $data['activity_id'];
        $already = LiveActivity::query()->where('project_id', $project->id)->where('activity_id', $activityId)
            ->whereIn('subscription_id', $devices->pluck('id'))->pluck('status', 'subscription_id');

        $state = $this->object($data['event_updates'] ?? []);
        $display = $this->display([], $data, start: true);
        $endAt = now()->addSeconds(min(self::MAX_DURATION, max(60, (int) ($data['end_after'] ?? self::MAX_DURATION))));
        // Until the person allows Live Activities, iOS gives no update token:
        // only the dates sent with the start act on the screen. Stale at the
        // planned end at least, rather than looking in progress for 8 hours.
        if (isset($data['end_after']) && ! isset($data['stale_date'])) {
            $data['stale_date'] = $endAt->getTimestamp();
        }
        $requests = ['ios' => [], 'android' => []];
        $activities = [];
        $alreadyCount = 0;
        $channels = [];

        foreach ($devices as $device) {
            // Started before (still open, or ended: an activity_id is used once).
            if ($already->has($device->id)) {
                $alreadyCount++;

                continue;
            }

            $android = $device->type === 'android';
            $activity = LiveActivity::create([
                'project_id' => $project->id,
                'subscription_id' => $device->id,
                'activity_type' => $type,
                'activity_id' => $activityId,
                'status' => 'starting',
                'content_state' => $android ? $display : $state,
                'started_at' => now(),
                'end_at' => $endAt,
            ]);
            $activities[$activity->id] = [$activity, $device];

            if ($android) {
                $requests['android'][$activity->id] = AndroidPayload::message((string) $device->token, 'start', $activity, $display, $device->person_language, $data);
            } else {
                $channel = null;
                if (self::supportsChannels($device)) {
                    $environment = $device->environment === 'sandbox' ? 'sandbox' : 'production';
                    $channels[$environment] ??= $this->channel($project, $apns, $activityId, $environment);
                    [$channel, $channelError] = $channels[$environment];
                    if ($channel !== null) {
                        $activity->channel_id = $channel;
                    } elseif ($channelError !== null) {
                        // Started with the token instead: why, on the activity.
                        $this->remember($activity, 'channel', Result::failed($channelError));
                    }
                }
                $requests['ios'][$activity->id] = [
                    'token' => $device->start_token,
                    'environment' => $device->environment,
                    ...Payload::start($apns, $type, $activityId, $data, $state, $device->person_language, $channel),
                ];
            }
        }

        $results = [
            ...($requests['ios'] ? $this->apns->push($apns, $requests['ios']) : []),
            ...($requests['android'] ? $this->fcm->push($fcm, $requests['android']) : []),
        ];

        $errors = [];
        foreach ($results as $id => $result) {
            [$activity, $device] = $activities[$id];
            $this->remember($activity, 'start', $result);

            if ($result->status === 'sent') {
                // Android and channels have no token to wait for: reachable at once.
                $activity->forceFill(['status' => $device->type === 'android' || $activity->channel_id ? 'active' : 'started'])->save();

                continue;
            }

            $activity->forceFill(['status' => 'failed', 'error' => $result->error, 'ended_at' => now()])->save();
            $errors[] = (string) $result->error;

            // A dead push-to-start token: the app sends a new one at its next launch.
            if ($result->invalid && $device->start_token_id) {
                LiveActivityStartToken::query()->whereKey($device->start_token_id)->delete();
            }
        }

        return [
            'recipients' => count($results) - count($errors),
            'already' => $alreadyCount,
            'targeted' => $targeted->count(),
            'skipped' => $leftOut,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * Update or end of an activity on every device showing it.
     *
     * @param  array<string, mixed>  $data
     * @return array{recipients: int, pending: int, errors: list<string>}|null null when no device shows it
     */
    public function notify(Project $project, string $activityId, string $event, array $data): ?array
    {
        $activities = LiveActivity::query()->with('subscription.subscriber')
            ->where('project_id', $project->id)->where('activity_id', $activityId)->open()->get();

        if ($activities->isEmpty()) {
            return null;
        }

        $pending = 0;
        $ready = new Collection;
        $event = $event === 'end' ? 'end' : 'update';

        foreach ($activities as $activity) {
            // The standard activity ("live"): what changes, on top of the state.
            $live = Template::live($data);
            if ($activity->subscription->type === 'android') {
                $android = $live ? [...Template::android($activity->content_state ?? [], $live), ...(array) ($data['android'] ?? [])] : null;
                $activity->content_state = $this->display($activity->content_state ?? [], $android ? [...$data, 'android' => $android] : $data);
            } elseif ($live) {
                $activity->content_state = [...Template::state($activity->content_state ?? [], $live), ...(array) ($data['event_updates'] ?? [])];
            } else {
                $state = array_key_exists('event_updates', $data) ? $this->object($data['event_updates']) : $activity->content_state;
                $activity->content_state = $state ?? [];
            }

            if (! $this->reachable($activity)) {
                // An end waiting for the token wins over any update after it.
                if (($activity->pending['event'] ?? null) !== 'end') {
                    $activity->pending = ['event' => $event, ...array_intersect_key($data, array_flip(Payload::KEYS))];
                }
                $activity->save();
                $pending++;

                continue;
            }

            if ($event === 'update' && $activity->updates >= self::MAX_UPDATES) {
                continue;
            }

            $activity->save();
            $ready->push($activity);
        }

        $results = $this->send($project, $ready, $event, $data);

        return [
            'recipients' => count(array_filter($results, fn (Result $result) => $result->status === 'sent')),
            'pending' => $pending,
            'errors' => array_values(array_unique(array_filter(array_map(fn (Result $result) => $result->error, $results)))),
        ];
    }

    /** The app (SDK) received the push-to-start token of a type of activity. */
    public function saveStartToken(Subscription $device, string $type, string $token): void
    {
        LiveActivityStartToken::query()->updateOrCreate(
            ['subscription_id' => $device->id, 'activity_type' => $type],
            ['project_id' => $device->project_id, 'token' => $token],
        );
    }

    public function removeStartToken(Subscription $device, string $type): void
    {
        LiveActivityStartToken::query()->where('subscription_id', $device->id)->where('activity_type', $type)->delete();
    }

    /**
     * The app is showing an activity and received its update token (started
     * by push, or by the app itself). What waited for the token leaves now.
     */
    public function enter(Subscription $device, string $activityId, string $type, string $token): LiveActivity
    {
        $activity = DB::transaction(function () use ($device, $activityId, $type, $token) {
            $activity = LiveActivity::query()->where('subscription_id', $device->id)->where('activity_id', $activityId)->lockForUpdate()->first()
                ?? new LiveActivity([
                    'project_id' => $device->project_id,
                    'subscription_id' => $device->id,
                    'activity_id' => $activityId,
                    'activity_type' => $type,
                    'started_at' => now(),
                    'end_at' => now()->addSeconds(self::MAX_DURATION),
                ]);

            // Ended by Carillon or the app while the token was on its way:
            // the activity must not stay on screen.
            $closed = $activity->exists && ! $activity->isOpen();
            $activity->fill([
                'update_token' => $token,
                'token_at' => now(),
                'status' => $closed ? $activity->status : 'active',
            ]);
            if ($closed) {
                $activity->pending = ['event' => 'end'];
            }
            $activity->save();

            return $activity;
        });

        if ($activity->pending) {
            $this->flush($activity);
        }

        return $activity;
    }

    /** The activity left the screen (ended by the app or dismissed by the person). */
    public function exit(Subscription $device, string $activityId): void
    {
        // Also the confirmation of an end sent by Carillon: no more retries.
        LiveActivity::query()->where('subscription_id', $device->id)->where('activity_id', $activityId)
            ->get()->each(fn (LiveActivity $activity) => $activity->forceFill([
                'status' => $activity->isOpen() ? 'ended' : $activity->status,
                'ended_at' => $activity->ended_at ?? now(),
                'update_token' => null,
                'pending' => null,
            ])->save());
    }

    /**
     * Ends the activities whose end_at passed, and forgets those started by
     * push whose token never came (the app was not launched since).
     */
    public function sweep(): int
    {
        $count = 0;

        LiveActivity::query()->with('subscription.subscriber', 'subscription.project.credentials')->open()
            ->where('end_at', '<=', now())->orderBy('end_at')->limit(500)->get()
            ->groupBy('project_id')
            ->each(function (Collection $activities) use (&$count) {
                [$reachable, $without] = $activities->partition(fn (LiveActivity $activity) => $this->reachable($activity));

                foreach ($without as $activity) {
                    $activity->forceFill(['status' => 'ended', 'ended_at' => now(), 'error' => $activity->error ?? 'Expired', 'pending' => ['event' => 'end']])->save();
                    $count++;
                }

                $this->send($activities->first()->subscription->project, $reachable, 'end', ['dismissal_date' => now()->getTimestamp()]);
                $count += $reachable->count();
            });

        // Ends not confirmed by the app: sent again (iOS ignores an end for
        // an activity already gone).
        LiveActivity::query()->with('subscription.subscriber', 'subscription.project.credentials')
            ->where('status', 'ended')->whereNotNull('update_token')->whereNull('pending')
            ->whereBetween('ends', [1, self::MAX_ENDS - 1])
            ->where('updated_last_at', '<=', now()->subMinutes(self::END_RETRY))
            ->where('ended_at', '>=', now()->subHours(8))
            ->limit(500)->get()
            ->groupBy('project_id')
            ->each(function (Collection $activities) use (&$count) {
                $this->send($activities->first()->subscription->project, $activities, 'end', ['dismissal_date' => now()->getTimestamp()]);
                $count += $activities->count();
            });

        return $count;
    }

    /** Sends what waited for the update token. */
    protected function flush(LiveActivity $activity): void
    {
        $pending = $activity->pending;
        $activity->forceFill(['pending' => null])->save();
        $activity->load('subscription.subscriber', 'subscription.project.credentials');

        $this->send($activity->subscription->project, collect([$activity]), $pending['event'] ?? 'update', $pending);
    }

    /** iPhones on a channel, or once the app gave the update token; Android at once (FCM token of the device). */
    protected function reachable(LiveActivity $activity): bool
    {
        return $activity->subscription->type === 'android' || filled($activity->channel_id) || filled($activity->update_token);
    }

    /** iOS 18+ (device_os "18.0", "iOS 26.5"…): an unknown version keeps the token. */
    public static function supportsChannels(Subscription $device): bool
    {
        return $device->type === 'ios'
            && preg_match('/(\d+)(?:\.\d+)*/', (string) $device->device_os, $version) === 1
            && (int) $version[1] >= self::CHANNEL_IOS;
    }

    /**
     * Broadcast channel of an activity_id: the same for every device of the
     * environment (a person with an iPhone and an iPad), created once.
     * Without the broadcast capability on the App ID, Apple refuses it:
     * channels are not tried again for a while, starts use the token.
     *
     * @return array{0: ?string, 1: ?string} channel, error
     */
    protected function channel(Project $project, PushCredential $apns, string $activityId, string $environment): array
    {
        $known = LiveActivityChannel::query()->where('project_id', $project->id)->where('activity_id', $activityId)
            ->where('environment', $environment)->whereNull('deleted_at')->value('channel_id');
        if ($known) {
            return [$known, null];
        }

        $pause = 'live-activities.channels-refused.'.$apns->id.'.'.$environment;
        if ($refused = Cache::get($pause)) {
            return [null, $refused];
        }

        ['channel' => $channel, 'error' => $error] = $this->apns->createChannel($apns, $environment);
        if ($channel === null) {
            Cache::put($pause, $error, now()->addMinutes(self::CHANNEL_PAUSE));

            return [null, $error];
        }

        LiveActivityChannel::query()->updateOrCreate(
            ['project_id' => $project->id, 'activity_id' => $activityId, 'environment' => $environment],
            ['channel_id' => $channel, 'deleted_at' => null],
        );

        return [$channel, null];
    }

    /**
     * Deletes at Apple the channels no longer needed (10 000 at most per app
     * and environment): every activity on it closed for an hour (stored
     * messages still arriving), or older than the longest activity.
     */
    public function deleteChannels(): int
    {
        $deleted = 0;

        LiveActivityChannel::query()->whereNull('deleted_at')->where('created_at', '<=', now()->subHour())
            ->orderBy('created_at')->limit(100)->get()
            ->each(function (LiveActivityChannel $channel) use (&$deleted) {
                $activities = LiveActivity::query()->where('channel_id', $channel->channel_id);
                $done = $channel->created_at->lte(now()->subSeconds(self::MAX_DURATION + 3600))
                    || (! (clone $activities)->open()->exists() && ! (clone $activities)->where('ended_at', '>', now()->subHour())->exists());
                if (! $done) {
                    return;
                }

                $apns = Project::query()->find($channel->project_id)?->credential('apns');
                if (! $apns?->isUsable() || $this->apns->deleteChannel($apns, $channel->environment, $channel->channel_id)) {
                    $channel->forceFill(['deleted_at' => now()])->save();
                    $deleted++;
                }
            });

        return $deleted;
    }

    /**
     * @param  Collection<int, LiveActivity>  $activities
     * @param  array<string, mixed>  $data
     * @return array<string, Result>
     */
    protected function send(Project $project, Collection $activities, string $event, array $data): array
    {
        if ($activities->isEmpty()) {
            return [];
        }

        [$apns, $fcm] = $this->credentials($project, required: false);
        $byId = $activities->keyBy('id');
        [$android, $ios] = $activities->partition(fn (LiveActivity $activity) => $activity->subscription->type === 'android');
        // A channel reaches every device on it with one request: the result
        // of the first activity is the result of all.
        [$broadcast, $direct] = $ios->partition(fn (LiveActivity $activity) => filled($activity->channel_id));
        $groups = $broadcast->groupBy('channel_id');
        $iosRequests = [];

        foreach ($apns ? $groups : [] as $channel => $group) {
            $first = $group->first();
            $iosRequests[$first->id] = [
                'channel' => (string) $channel,
                'environment' => $first->subscription->environment,
                ...Payload::event($apns, $event, $data, $first->content_state ?? [], $first->subscription->subscriber?->language),
            ];
            // An end waits on the channel as long as Apple keeps messages.
            if ($event === 'end') {
                $iosRequests[$first->id]['headers']['apns-expiration'] = (string) (time() + self::MAX_DURATION);
            }
        }
        foreach ($apns ? $direct : [] as $activity) {
            $iosRequests[$activity->id] = [
                'token' => $activity->update_token,
                'environment' => $activity->subscription->environment,
                ...Payload::event($apns, $event, $data, $activity->content_state ?? [], $activity->subscription->subscriber?->language),
            ];
        }

        $results = [
            ...($apns && $iosRequests ? $this->apns->push($apns, $iosRequests) : []),
            ...($fcm && $android->isNotEmpty() ? $this->fcm->push($fcm, $android->mapWithKeys(fn (LiveActivity $activity) => [
                $activity->id => AndroidPayload::message((string) $activity->subscription->token, $event, $activity, $activity->content_state ?? [], $activity->subscription->subscriber?->language, $data),
            ])->all()) : []),
        ];
        foreach ($groups as $group) {
            foreach ($group->skip(1) as $activity) {
                if (isset($results[$group->first()->id])) {
                    $results[$activity->id] = $results[$group->first()->id];
                }
            }
        }

        foreach ($results as $id => $result) {
            $activity = $byId[$id];
            $this->remember($activity, $event, $result);

            if ($result->status === 'sent') {
                // The token is kept after an end: sent again until the app
                // confirms (exit), Apple accepting an end that never arrives.
                $activity->forceFill($event === 'end'
                    ? ['status' => 'ended', 'ended_at' => $activity->ended_at ?? now(), 'pending' => null, 'ends' => $activity->ends + 1, 'updated_last_at' => now()]
                    : ['updates' => $activity->updates + 1, 'updated_last_at' => now()])->save();
            } elseif ($result->invalid) {
                // The activity (or the app) is gone from the device.
                $activity->forceFill(['status' => 'ended', 'ended_at' => $activity->ended_at ?? now(), 'update_token' => null, 'pending' => null, 'error' => $result->error])->save();
            } else {
                $activity->forceFill(['error' => $result->error])->save();
            }
        }

        return $results;
    }

    /** Keeps the last answers of Apple / Google on the activity (saved with it). */
    protected function remember(LiveActivity $activity, string $event, Result $result): void
    {
        $push = ['event' => $event, 'at' => now()->toIso8601String(), 'result' => $result->status, 'error' => $result->error, ...$result->response];
        $activity->pushes = array_slice([array_filter($push, fn ($value) => $value !== null), ...($activity->pushes ?? [])], 0, 10);
    }

    /**
     * APNs and FCM credentials usable for Live Activities.
     *
     * @return array{0: ?PushCredential, 1: ?PushCredential}
     */
    protected function credentials(Project $project, bool $required = true): array
    {
        $apns = $project->credential('apns');
        $fcm = $project->credential('fcm');
        $apns = $apns?->isUsable() ? $apns : null;
        $fcm = $fcm?->isUsable() ? $fcm : null;
        abort_if($required && ! $apns && ! $fcm, 400, 'Neither Apple Push (APNs) nor Firebase (FCM) is configured for this app.');

        return [$apns, $fcm];
    }

    /**
     * What Android shows: the "android" object of the request on top of
     * the previous display; at the start, headings / contents by default.
     *
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function display(array $previous, array $data, bool $start = false): array
    {
        $android = is_array($data['android'] ?? null) ? array_intersect_key($data['android'], array_flip(AndroidPayload::DISPLAY)) : [];
        $display = [...$previous, ...$android];

        if ($start) {
            $display['title'] ??= $data['headings'] ?? $data['name'] ?? null;
            $display['body'] ??= $data['contents'] ?? null;
        }
        if (array_key_exists('data', $data) && is_array($data['data'])) {
            $display['data'] = $data['data'];
        }

        return array_filter($display, fn ($value) => $value !== null);
    }

    /**
     * Targeting fields of the OneSignal format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function targeting(array $data): array
    {
        return array_filter([
            'subscription_ids' => array_values(array_filter((array) ($data['include_subscription_ids'] ?? []), 'is_string')),
            'external_ids' => array_values(array_filter((array) ($data['include_aliases']['external_id'] ?? $data['include_external_user_ids'] ?? []), 'is_string')),
            'segments' => (array) ($data['included_segments'] ?? []),
            'excluded_segments' => (array) ($data['excluded_segments'] ?? []),
            'filters' => (array) ($data['filters'] ?? []),
        ]);
    }

    /** A JSON object, even when empty (ActivityKit decodes a dictionary). */
    protected function object(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** Localized text of a message field for a device. */
    public static function text(mixed $texts, ?string $language): ?string
    {
        return is_array($texts) ? Message::localized($texts, $language) : (is_string($texts) ? $texts : null);
    }
}
