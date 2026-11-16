<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A banner or pop-up shown inside the open app, displayed by the SDK. */
#[Fillable(['project_id', 'created_by', 'name', 'status', 'layout', 'title', 'body', 'image_url', 'buttons', 'trigger', 'segment', 'frequency', 'frequency_days', 'starts_at', 'ends_at'])]
class InAppMessage extends Model
{
    use HasUuids;

    public const LAYOUTS = ['banner', 'modal', 'fullscreen'];

    public const FREQUENCIES = ['once', 'every_session', 'every_days'];

    protected function casts(): array
    {
        return [
            'buttons' => 'array',
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

    /** What the SDKs receive. @return array<string, mixed> */
    public function toSdk(): array
    {
        return array_filter([
            'id' => $this->id,
            'layout' => $this->layout,
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $this->image_url,
            'buttons' => $this->buttons ?: null,
            'trigger' => $this->trigger,
            'frequency' => $this->frequency,
            'frequency_days' => $this->frequency === 'every_days' ? $this->frequency_days : null,
            'ends_at' => $this->ends_at?->toIso8601String(),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
