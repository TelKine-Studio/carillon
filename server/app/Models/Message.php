<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A notification sent (or to send) to part of a project's audience. */
#[Fillable(['project_id', 'created_by', 'api_key_id', 'name', 'idempotency_key', 'headings', 'subtitle', 'contents', 'options', 'targeting', 'status', 'send_after', 'recipients', 'successful', 'failed', 'errored', 'received', 'clicked', 'error', 'started_at', 'completed_at', 'canceled_at'])]
class Message extends Model
{
    use HasUuids;

    public const STATUSES = ['scheduled', 'queued', 'sending', 'sent', 'canceled', 'failed'];

    protected function casts(): array
    {
        return [
            'headings' => 'array',
            'subtitle' => 'array',
            'contents' => 'array',
            'options' => 'array',
            'targeting' => 'array',
            'send_after' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<MessagePlatform, $this> */
    public function platformStats(): HasMany
    {
        return $this->hasMany(MessagePlatform::class);
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }

    /**
     * Text for a language, falling back to English then to the first one.
     *
     * @param  array<string, string>|null  $texts
     */
    public static function localized(?array $texts, ?string $language): ?string
    {
        if (! $texts) {
            return null;
        }

        $language = strtolower(substr((string) $language, 0, 2));

        return $texts[$language] ?? $texts['en'] ?? reset($texts) ?: null;
    }

    public function isCancelable(): bool
    {
        return in_array($this->status, ['scheduled', 'queued'], true);
    }

    /** Remaining devices to send to. */
    public function remaining(): int
    {
        return max(0, $this->recipients - $this->successful - $this->failed - $this->errored);
    }

    /** Short description of who receives the message. */
    public function audienceLabel(): string
    {
        $t = $this->targeting ?? [];

        $label = match (true) {
            ! empty($t['subscription_ids']) => trans_choice(':count device|:count devices', count($t['subscription_ids'])),
            ! empty($t['external_ids']) => trans_choice(':count user|:count users', count($t['external_ids'])),
            ! empty($t['segments']) => implode(', ', array_map(fn ($s) => __($s), $t['segments'])),
            ! empty($t['filters']) => __('Filters'),
            default => '—',
        };

        if (! empty($t['excluded_segments'])) {
            $label .= ' − '.implode(', ', array_map(fn ($s) => __($s), $t['excluded_segments']));
        }

        if (! empty($t['platforms'])) {
            $label .= ' · '.implode(', ', array_map(fn ($p) => ['ios' => 'iOS', 'android' => 'Android', 'web' => 'Web'][$p] ?? $p, $t['platforms']));
        }

        return $label;
    }

    /** Title shown in the lists: name, else heading, else text. */
    public function label(): string
    {
        return $this->name ?: (self::localized($this->headings, app()->getLocale()) ?? mb_strimwidth((string) self::localized($this->contents, app()->getLocale()), 0, 80, '…'));
    }

    public function rate(int $value): ?float
    {
        return $this->successful > 0 ? round($value / $this->successful * 100, 1) : null;
    }
}
