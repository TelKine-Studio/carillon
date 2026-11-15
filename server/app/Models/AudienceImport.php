<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file of subscribers exported from OneSignal, imported in the background. */
#[Fillable(['project_id', 'user_id', 'source', 'filename', 'path', 'status', 'rows', 'created', 'updated', 'skipped', 'notes', 'error', 'started_at', 'completed_at'])]
class AudienceImport extends Model
{
    protected function casts(): array
    {
        return ['notes' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function running(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
