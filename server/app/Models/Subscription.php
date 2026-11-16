<?php

namespace App\Models;

use App\Audience\Activity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A device or browser able to receive notifications (OneSignal "subscription"). */
#[Fillable(['project_id', 'subscriber_id', 'type', 'token', 'environment', 'enabled', 'invalid_at', 'app_version', 'device_model', 'device_os', 'sdk', 'sessions', 'last_active_at', 'is_test', 'test_name', 'onesignal_id'])]
#[Hidden(['token', 'token_hash'])]
class Subscription extends Model
{
    use HasUuids;

    public const TYPES = ['ios', 'android', 'huawei', 'amazon', 'web', 'email', 'sms'];

    /** Types reached by push notifications (the email and SMS channels are apart). */
    public const PUSH_TYPES = ['ios', 'android', 'huawei', 'amazon', 'web'];

    /** Provider handling each type of device. */
    public const PROVIDERS = ['ios' => 'apns', 'android' => 'fcm', 'huawei' => 'hms', 'amazon' => 'adm', 'web' => 'webpush', 'email' => 'email', 'sms' => 'sms'];

    /** Marks a device imported from OneSignal that the Carillon SDK has not registered yet. */
    public const ONESIGNAL_SDK = 'onesignal-import';

    protected static function booted(): void
    {
        static::saving(function (Subscription $subscription) {
            $subscription->token_hash = $subscription->token ? hash('sha256', $subscription->token) : null;
        });
        static::saved(function (Subscription $subscription) {
            if ($subscription->subscriber_id && $subscription->last_active_at && $subscription->wasChanged('last_active_at') && ! $subscription->wasRecentlyCreated) {
                Activity::record($subscription->subscriber_id, $subscription->project_id, $subscription->last_active_at);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'enabled' => 'boolean',
            'invalid_at' => 'datetime',
            'last_active_at' => 'datetime',
            'sessions' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Subscriber, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * Devices that can receive a notification right now.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeReachable(Builder $query): void
    {
        $query->where('enabled', true)->whereNull('invalid_at')->whereNotNull('token');
    }

    /**
     * The app on this device still runs the OneSignal SDK (imported device,
     * app not updated yet): it only displays OneSignal-formatted pushes.
     */
    public function usesOneSignalSdk(): bool
    {
        return $this->sdk === self::ONESIGNAL_SDK;
    }

    public function provider(): string
    {
        return self::PROVIDERS[$this->type];
    }

    /** The web push subscription (endpoint and keys). */
    public function webSubscription(): ?array
    {
        return $this->type === 'web' ? json_decode((string) $this->token, true) : null;
    }
}
