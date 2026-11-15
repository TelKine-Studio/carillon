<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A person using an app (OneSignal "user"), with their devices and tags. */
#[Fillable(['project_id', 'external_id', 'tags', 'language', 'timezone', 'country', 'last_active_at'])]
class Subscriber extends Model
{
    use HasUuids;

    /** Tags kept per person (OneSignal's free plan allows 2, paid plans 10-100). */
    public const MAX_TAGS = 100;

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'last_active_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Merges tags: a null or empty value deletes the tag (OneSignal behavior).
     *
     * @param  array<string, mixed>  $tags
     */
    public function mergeTags(array $tags): void
    {
        $current = $this->tags ?? [];

        foreach ($tags as $key => $value) {
            $key = mb_substr((string) $key, 0, 128);

            if ($value === null || $value === '') {
                unset($current[$key]);
            } else {
                $current[$key] = is_scalar($value) ? (string) $value : json_encode($value);
            }
        }

        $this->tags = array_slice($current, 0, self::MAX_TAGS, true) ?: null;
    }
}
