<?php

use App\Jobs\RunIntegration;
use App\Jobs\SyncOneSignal;
use App\Journeys\JourneyRunner;
use App\LiveActivities\LiveActivities;
use App\Models\AudienceImport;
use App\Models\Integration;
use App\Models\OneSignalSync;
use App\Support\SystemHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Delete old delivery history and stale invitations.
Schedule::command('model:prune')->daily()->withoutOverlapping();

// Sign of life of the scheduler, shown in Settings → System.
Schedule::call(fn () => Cache::put(SystemHealth::SCHEDULER_KEY, now()->getTimestamp(), now()->addHour()))->everyMinute()->name('scheduler-heartbeat');

// Summary of the week, Monday morning.
Schedule::command('carillon:weekly-reports')->weeklyOn(1, '07:00')->withoutOverlapping();

// Messages scheduled with send_after.
Schedule::command('carillon:dispatch-scheduled')->everyMinute()->withoutOverlapping();

// New subscribers of apps still running the OneSignal SDK, at the frequency of each project.
Schedule::call(function () {
    OneSignalSync::query()->where('enabled', true)->whereNull('pending_url')->each(function (OneSignalSync $sync) {
        if (! $sync->isDue(now())) {
            return;
        }
        $sync->forceFill(['requested_at' => now(), 'last_error' => null])->save();
        SyncOneSignal::dispatch($sync->id);
    });
})->hourlyAt(OneSignalSync::MINUTE)->name('onesignal-sync')->withoutOverlapping();

// Imports whose worker died (redeploy, crash) are marked failed instead of "running" forever.
Schedule::call(function () {
    AudienceImport::query()->whereIn('status', ['queued', 'running'])->where('updated_at', '<', now()->subHours(AudienceImport::STALL_HOURS))
        ->update(['status' => 'failed', 'error' => __('The import was interrupted. Run it again.'), 'completed_at' => now()]);
})->hourly()->name('audience-imports-stalled');

// Downloads the OneSignal exports once generated (a few minutes after the request).
Schedule::call(function () {
    OneSignalSync::query()->whereNotNull('pending_url')->pluck('id')->each(fn (int $id) => SyncOneSignal::dispatch($id));
})->everyMinute()->name('onesignal-sync-download')->withoutOverlapping();

// Journeys: people whose step is due move on; inactive people enter.
Schedule::call(fn () => app(JourneyRunner::class)->run())->everyMinute()->name('journeys-run')->withoutOverlapping();
Schedule::call(fn () => app(JourneyRunner::class)->sweepInactive())->hourly()->name('journeys-inactive')->withoutOverlapping();

// Live Activities nobody ended (end_after passed): ended by Carillon.
Schedule::call(fn () => app(LiveActivities::class)->sweep())->everyMinute()->name('live-activities-sweep')->withoutOverlapping();

// Integrations: SQL syncs (hourly, or daily at 03:00) and exports to S3 (04:15, the day before).
Schedule::call(function () {
    Integration::query()->where('enabled', true)->whereIn('kind', ['postgres', 'mysql'])->get()
        ->filter(fn (Integration $integration) => $integration->setting('schedule', 'daily') === 'hourly' || now()->hour === 3)
        ->each(fn (Integration $integration) => RunIntegration::dispatch($integration->id));
})->hourly()->name('integrations-sql')->withoutOverlapping();
Schedule::call(function () {
    Integration::query()->where('enabled', true)->where('kind', 's3')->pluck('id')
        ->each(fn (int $id) => RunIntegration::dispatch($id, now()->subDay()->toDateString()));
})->dailyAt('04:15')->name('integrations-s3')->withoutOverlapping();
