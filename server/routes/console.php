<?php

use App\Jobs\SyncOneSignal;
use App\Journeys\JourneyRunner;
use App\Models\OneSignalSync;
use Illuminate\Support\Facades\Schedule;

// Delete old delivery history and stale invitations.
Schedule::command('model:prune')->daily()->withoutOverlapping();

// Messages scheduled with send_after.
Schedule::command('carillon:dispatch-scheduled')->everyMinute()->withoutOverlapping();

// New subscribers of apps still running the OneSignal SDK, every night.
Schedule::call(function () {
    OneSignalSync::query()->where('enabled', true)->whereNull('pending_url')->each(function (OneSignalSync $sync) {
        $sync->forceFill(['requested_at' => now(), 'last_error' => null])->save();
        SyncOneSignal::dispatch($sync->id);
    });
})->dailyAt('03:30')->name('onesignal-sync')->withoutOverlapping();

// Downloads the OneSignal exports once generated (a few minutes after the request).
Schedule::call(function () {
    OneSignalSync::query()->whereNotNull('pending_url')->pluck('id')->each(fn (int $id) => SyncOneSignal::dispatch($id));
})->everyMinute()->name('onesignal-sync-download')->withoutOverlapping();

// Journeys: people whose step is due move on; inactive people enter.
Schedule::call(fn () => app(JourneyRunner::class)->run())->everyMinute()->name('journeys-run')->withoutOverlapping();
Schedule::call(fn () => app(JourneyRunner::class)->sweepInactive())->hourly()->name('journeys-inactive')->withoutOverlapping();
