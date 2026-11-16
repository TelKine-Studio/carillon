<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Two versions of a message sent to a sample; the winner goes to the rest. */
#[Fillable(['project_id', 'created_by', 'name', 'sample', 'remaining', 'winner_id'])]
class AbTest extends Model
{
    protected function casts(): array
    {
        return ['remaining' => 'array'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('ab_variant');
    }

    public function variant(string $letter): ?Message
    {
        return $this->messages->firstWhere('ab_variant', $letter);
    }

    /** The version with the best click rate so far, if they differ. */
    public function leader(): ?Message
    {
        [$a, $b] = [$this->variant('A'), $this->variant('B')];
        if (! $a || ! $b) {
            return null;
        }
        $rate = fn (Message $m) => $m->successful > 0 ? $m->clicked / $m->successful : 0;

        return match ($rate($a) <=> $rate($b)) {
            1 => $a,
            -1 => $b,
            default => null,
        };
    }
}
