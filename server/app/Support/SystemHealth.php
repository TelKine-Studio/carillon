<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/** State of the instance: database, queue, scheduler, storage, mail. */
final class SystemHealth
{
    /** Written every minute by the scheduler. */
    public const SCHEDULER_KEY = 'carillon:scheduler-heartbeat';

    /** @return array<string, mixed> */
    public static function check(): array
    {
        $database = rescue(function () {
            DB::select('select 1');

            return ['ok' => true, 'driver' => DB::connection()->getDriverName()];
        }, fn (Throwable $e) => ['ok' => false, 'driver' => DB::connection()->getDriverName(), 'error' => mb_substr($e->getMessage(), 0, 160)], false);

        $pending = rescue(fn () => DB::table('jobs')->count(), null, false);
        $failed = rescue(fn () => DB::table('failed_jobs')->count(), null, false);
        $oldest = rescue(fn () => DB::table('jobs')->min('created_at'), null, false);
        $lastRun = Cache::get(self::SCHEDULER_KEY);
        $path = storage_path();
        $free = @disk_free_space($path) ?: null;
        $total = @disk_total_space($path) ?: null;

        $checks = [
            'database' => $database,
            'queue' => [
                // Jobs waiting for more than 5 minutes: the worker is late or stopped.
                'ok' => $oldest === null || now()->getTimestamp() - (int) $oldest < 300,
                'driver' => config('queue.default'),
                'pending' => $pending,
                'failed' => $failed,
            ],
            'scheduler' => [
                'ok' => $lastRun !== null && now()->getTimestamp() - (int) $lastRun < 180,
                'last_run' => $lastRun ? now()->setTimestamp((int) $lastRun)->toIso8601String() : null,
            ],
            'storage' => [
                'ok' => $free === null || $free > 500 * 1024 * 1024,
                'free' => $free,
                'total' => $total,
            ],
            'mail' => [
                'ok' => config('mail.default') !== 'log',
                'driver' => config('mail.default'),
            ],
        ];

        return [
            'ok' => collect($checks)->every(fn ($check) => $check['ok']),
            'version' => config('carillon.version'),
            'php' => PHP_VERSION,
            'checks' => $checks,
        ];
    }
}
