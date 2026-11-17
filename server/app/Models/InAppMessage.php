<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A banner or pop-up shown inside the open app, displayed by the SDK. */
#[Fillable(['project_id', 'created_by', 'name', 'status', 'layout', 'title', 'body', 'image_url', 'buttons', 'variant_b', 'trigger', 'segment', 'frequency', 'frequency_days', 'starts_at', 'ends_at', 'journey_only'])]
class InAppMessage extends Model
{
    use HasUuids;

    public const LAYOUTS = ['banner', 'modal', 'fullscreen'];

    public const FREQUENCIES = ['once', 'every_session', 'every_days'];

    protected function casts(): array
    {
        return [
            'buttons' => 'array',
            'journey_only' => 'boolean',
            'variant_b' => 'array',
            'trigger' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Active and within its dates. */
    public function isLive(): bool
    {
        return $this->status === 'active'
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->ends_at || $this->ends_at->isFuture());
    }

    /** Half of the people (always the same ones) see version B when there is one. */
    public function variantFor(string $subscriptionId): string
    {
        return $this->variant_b && crc32($this->id.$subscriptionId) % 2 === 1 ? 'b' : 'a';
    }

    /** Version B, A/B tested against the main text. */
    public function hasAbTest(): bool
    {
        return filled($this->variant_b['body'] ?? null);
    }

    /** What the SDKs receive (the version of this device). @return array<string, mixed> */
    public function toSdk(?string $subscriptionId = null): array
    {
        $variant = $subscriptionId && $this->hasAbTest() ? $this->variantFor($subscriptionId) : 'a';
        $b = $variant === 'b' ? $this->variant_b : [];

        return array_filter([
            'id' => $this->id,
            'variant' => $this->hasAbTest() ? $variant : null,
            'layout' => $this->layout,
            'title' => array_key_exists('title', $b) ? $b['title'] : $this->title,
            'body' => $b['body'] ?? $this->body,
            'image_url' => $this->image_url,
            'buttons' => $this->buttons ?: null,
            'trigger' => $this->trigger,
            'frequency' => $this->frequency,
            'frequency_days' => $this->frequency === 'every_days' ? $this->frequency_days : null,
            'ends_at' => $this->ends_at?->toIso8601String(),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
