<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An automation: a trigger, then steps each person goes through (OneSignal "Journeys"). */
#[Fillable(['project_id', 'created_by', 'name', 'status', 'trigger', 'segment', 'reentry', 'steps', 'activated_at'])]
class Journey extends Model
{
    public const TRIGGERS = ['new_device', 'inactive', 'tag', 'event'];

    public const STEPS = ['wait', 'push', 'email', 'condition'];

    protected function casts(): array
    {
        return [
            'trigger' => 'array',
            'steps' => 'array',
            'reentry' => 'boolean',
            'activated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<JourneyEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(JourneyEntry::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Readable summary of the trigger ("New device", "Inactive for 7 days"…). */
    public function triggerLabel(): string
    {
        $trigger = $this->trigger ?? [];

        return match ($trigger['type'] ?? null) {
            'new_device' => __('A device subscribes'),
            'inactive' => trans_choice('Inactive for :count day|Inactive for :count days', (int) ($trigger['days'] ?? 7), ['count' => (int) ($trigger['days'] ?? 7)]),
            'tag' => __('Tag :key = :value', ['key' => $trigger['key'] ?? '?', 'value' => $trigger['value'] ?? '*']),
            'event' => __('Event :name', ['name' => $trigger['event'] ?? '?']),
            default => '—',
        };
    }
}
