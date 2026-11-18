<?php

namespace App\Models;

use App\Webhooks\Webhooks;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Automatic import of the subscribers of a OneSignal app (CSV export API). */
#[Fillable(['project_id', 'app_id', 'api_key', 'enabled', 'every_hours', 'at_hour', 'pending_url', 'requested_at', 'last_synced_at', 'last_error'])]
class OneSignalSync extends Model
{
    protected $table = 'onesignal_syncs';

    /** Hours between two syncs that can be chosen (24 = once a day, at at_hour). */
    public const FREQUENCIES = [1, 3, 6, 12, 24];

    /** Minute of the hour the scheduler runs the syncs. */
    public const MINUTE = 30;

    protected $hidden = ['api_key'];

    protected $attributes = ['every_hours' => 24, 'at_hour' => 3];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
            'every_hours' => 'integer',
            'at_hour' => 'integer',
            'requested_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A failing nightly sync is told once (not at every retry of the same error).
        static::saved(function (OneSignalSync $sync) {
            if ($sync->last_error && $sync->wasChanged('last_error')) {
                Webhooks::dispatch($sync->project_id, 'onesignal_sync.failed', [
                    'onesignal_app_id' => $sync->app_id,
                    'error' => $sync->last_error,
                    'last_synced_at' => $sync->last_synced_at?->toIso8601String(),
                ]);
            }
        });
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** An export was requested and its file is not downloaded yet. */
    public function isRunning(): bool
    {
        return $this->pending_url !== null;
    }

    /** Time for the next sync (the scheduler checks at :30 of every hour). */
    public function isDue(CarbonInterface $now): bool
    {
        if (! $this->enabled || $this->isRunning()) {
            return false;
        }

        if ($this->every_hours >= 24) {
            return $now->hour === $this->at_hour && (! $this->requested_at || $this->requested_at->lt($now->copy()->subHours(12)));
        }

        // A few minutes of slack: a sync started at 10:31 is due again at 11:30.
        return ! $this->requested_at || $this->requested_at->lte($now->copy()->subHours($this->every_hours)->addMinutes(10));
    }

    public function frequencyLabel(): string
    {
        return self::frequencyName($this->every_hours, $this->at_hour);
    }

    public static function frequencyName(int $hours, int $atHour = 3): string
    {
        return match (true) {
            $hours >= 24 => __('Every day at :time (UTC)', ['time' => sprintf('%02d:%02d', $atHour, self::MINUTE)]),
            $hours === 1 => __('Every hour'),
            default => __('Every :count hours', ['count' => $hours]),
        };
    }
}
