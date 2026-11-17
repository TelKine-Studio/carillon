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

    public const TYPES = ['ios', 'android', 'huawei', 'amazon', 'web', 'email', 'sms', 'whatsapp'];

    /** Types reached by push notifications (the email and SMS channels are apart). */
    public const PUSH_TYPES = ['ios', 'android', 'huawei', 'amazon', 'web'];

    /** Provider handling each type of device. */
    public const PROVIDERS = ['ios' => 'apns', 'android' => 'fcm', 'huawei' => 'hms', 'amazon' => 'adm', 'web' => 'webpush', 'email' => 'email', 'sms' => 'sms', 'whatsapp' => 'whatsapp'];

    /** Decimals kept of the coordinates: 2 ≈ 1.1 km ("approximate location" for Apple). */
    public const LOCATION_DECIMALS = 2;

    /** Marks a device imported from OneSignal that the Carillon SDK has not registered yet. */
    public const ONESIGNAL_SDK = 'onesignal-import';

    /**
     * Why a device is unsubscribed or unreachable (OneSignal "status details").
     * "rejected" keeps the answer of the provider in status_detail.
     */
    public const REASONS = [
        'rejected', 'token_reused', 'email_bounced', 'email_complaint', 'email_unsubscribed',
        'permission_denied', 'permission_not_asked', 'opted_out', 'disabled_on_device',
        'disabled_by_api', 'imported_unsubscribed',
    ];

    protected static function booted(): void
    {
        static::saving(function (Subscription $subscription) {
            $subscription->token_hash = $subscription->token ? hash('sha256', $subscription->token) : null;

            // First subscription ("never subscribed" until then), last unsubscription.
            if ($subscription->enabled && $subscription->invalid_at === null) {
                $subscription->subscribed_at ??= now();
            }
            if (($subscription->isDirty('enabled') && ! $subscription->enabled && $subscription->getOriginal('enabled'))
                || ($subscription->isDirty('invalid_at') && $subscription->invalid_at !== null)) {
                $subscription->unsubscribed_at = now();
            }

            // Reachable again: the reason no longer applies.
            if ($subscription->enabled && $subscription->invalid_at === null && $subscription->status_reason !== null && ! $subscription->isDirty('status_reason')) {
                $subscription->setStatus(null);
            }
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
            'seen_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'location_accuracy' => 'integer',
            'located_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'usage_seconds' => 'integer',
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

    /**
     * The app shows Live Activities as Live Updates (Android, Carillon
     * Flutter SDK 0.3.0 or later).
     */
    public function supportsLiveUpdates(): bool
    {
        return $this->type === 'android'
            && preg_match('#^carillon-flutter/(\d+)\.(\d+)\.(\d+)#', (string) $this->sdk, $version) === 1
            && version_compare("{$version[1]}.{$version[2]}.{$version[3]}", '0.3.0', '>=');
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

    /** Stores the position (rounded), or forgets it with nulls. */
    public function locate(?float $latitude, ?float $longitude, ?float $accuracy = null): void
    {
        $known = $latitude !== null && $longitude !== null;
        $this->forceFill([
            'latitude' => $known ? round($latitude, self::LOCATION_DECIMALS) : null,
            'longitude' => $known ? round($longitude, self::LOCATION_DECIMALS) : null,
            'location_accuracy' => $known && $accuracy !== null ? (int) max(round($accuracy), 1100) : null,
            'located_at' => $known ? now() : null,
        ]);
    }

    /** Records why the device is (no longer) reachable; null when it is subscribed. */
    public function setStatus(?string $reason, ?string $detail = null): static
    {
        return $this->forceFill([
            'status_reason' => $reason,
            'status_detail' => $reason === null || $detail === null ? null : mb_substr($detail, 0, 255),
            'status_changed_at' => now(),
        ]);
    }

    /** Readable status details, or null for a subscribed device. */
    public function statusDetails(): ?string
    {
        if ($this->enabled && $this->invalid_at === null) {
            return null;
        }

        $provider = match ($this->type) {
            'ios' => 'Apple', 'android' => 'Google', 'huawei' => 'Huawei', 'amazon' => 'Amazon',
            'web' => __('the browser'), 'email' => __('the email service'), 'sms' => __('the SMS provider'), 'whatsapp' => 'WhatsApp',
            default => $this->type,
        };

        return match ($this->status_reason) {
            'rejected' => __('Refused by :provider', ['provider' => $provider]).($this->status_detail ? ' : '.$this->status_detail : ''),
            'token_reused' => __('Token taken by another registration (app reinstalled)'),
            'email_bounced' => __('Email address bounced').($this->status_detail ? ' : '.$this->status_detail : ''),
            'email_complaint' => __('Reported as spam'),
            'email_unsubscribed' => __('Unsubscribed with the link of an email'),
            'permission_denied' => __('Notifications turned off on the device'),
            'permission_not_asked' => __('Permission never asked by the app'),
            'opted_out' => __('Unsubscribed by the app (optOut)'),
            'disabled_on_device' => __('Disabled on the device'),
            'disabled_by_api' => __('Disabled through the API'),
            'imported_unsubscribed' => __('Unsubscribed in OneSignal'),
            default => __('Reason not recorded'),
        };
    }
}
