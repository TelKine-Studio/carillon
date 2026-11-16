<?php

namespace App\Console\Commands;

use App\LiveActivities\LiveActivities;
use App\Models\LiveActivity;
use App\Models\LiveActivityStartToken;
use App\Models\Project;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Drives a Live Activity from the server (tests of an app, operations)
 * without an API key: same effects as the API.
 */
#[Signature('carillon:live-activity
    {event : start, update, end or status}
    {app_id : App ID of the project}
    {activity_id : id of the activity}
    {--type= : activity_type (start)}
    {--subscription=* : device ids targeted (start)}
    {--external-id=* : external ids targeted (start)}
    {--title= : alert title on iOS, title on Android}
    {--body= : alert text on iOS, text on Android}
    {--state= : ContentState as JSON}
    {--attributes= : attributes as JSON (start)}
    {--android= : android display object as JSON}
    {--stale= : stale_date in seconds from now}
    {--dismiss= : dismissal_date in seconds from now}
    {--end-after= : seconds before Carillon ends it (start)}
    {--priority=10 : 10 or 5 (update)}')]
#[Description('Starts, updates or ends a Live Activity, or shows where it is (tests and operations)')]
class LiveActivityCommand extends Command
{
    public function handle(LiveActivities $activities): int
    {
        $project = Project::query()->where('app_id', $this->argument('app_id'))->first();
        if (! $project) {
            $this->error('Unknown app_id.');

            return self::FAILURE;
        }

        $event = $this->argument('event');
        $activityId = $this->argument('activity_id');

        if ($event === 'status') {
            return $this->status($project, $activityId);
        }

        $data = array_filter([
            'activity_id' => $activityId,
            'headings' => $this->option('title') !== null ? ['en' => $this->option('title')] : null,
            'contents' => $this->option('body') !== null ? ['en' => $this->option('body')] : null,
            'event_updates' => $this->json('state'),
            'event_attributes' => $this->json('attributes'),
            'android' => $this->json('android'),
            'stale_date' => $this->option('stale') !== null ? now()->addSeconds((int) $this->option('stale'))->getTimestamp() : null,
            'dismissal_date' => $this->option('dismiss') !== null ? now()->addSeconds((int) $this->option('dismiss'))->getTimestamp() : null,
            'end_after' => $this->option('end-after') !== null ? (int) $this->option('end-after') : null,
            'priority' => (int) $this->option('priority'),
            'include_subscription_ids' => $this->option('subscription') ?: null,
            'include_aliases' => $this->option('external-id') ? ['external_id' => $this->option('external-id')] : null,
        ], fn ($value) => $value !== null);

        $result = match ($event) {
            'start' => $activities->start($project, (string) $this->option('type'), $data),
            'update', 'end' => $activities->notify($project, $activityId, $event, $data),
            default => false,
        };

        if ($result === false) {
            $this->error('Event: start, update, end or status.');

            return self::FAILURE;
        }
        if ($result === null) {
            $this->warn('No device shows this activity (never started, or ended).');

            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));
        $this->status($project, $activityId);

        return self::SUCCESS;
    }

    protected function status(Project $project, string $activityId): int
    {
        $rows = LiveActivity::query()->with('subscription')->where('project_id', $project->id)->where('activity_id', $activityId)->get();
        $this->table(['device', 'type', 'status', 'token', 'updates', 'error', 'started', 'ended'], $rows->map(fn (LiveActivity $activity) => [
            $activity->subscription_id,
            $activity->subscription?->type,
            $activity->status,
            $activity->update_token ? 'yes' : 'no',
            $activity->updates,
            $activity->error,
            $activity->started_at?->toDateTimeString(),
            $activity->ended_at?->toDateTimeString(),
        ])->all());

        $tokens = LiveActivityStartToken::query()->where('project_id', $project->id)->get(['subscription_id', 'activity_type', 'updated_at']);
        $this->line('Push-to-start tokens: '.($tokens->isEmpty() ? 'none' : $tokens->map(fn ($token) => "{$token->subscription_id} ({$token->activity_type}, {$token->updated_at})")->implode(', ')));

        return self::SUCCESS;
    }

    protected function json(string $option): ?array
    {
        $value = $this->option($option);

        return $value === null ? null : (json_decode($value, true) ?: []);
    }
}
