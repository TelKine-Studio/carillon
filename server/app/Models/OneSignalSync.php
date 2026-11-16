<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Automatic import of the subscribers of a OneSignal app (CSV export API). */
#[Fillable(['project_id', 'app_id', 'api_key', 'enabled', 'pending_url', 'requested_at', 'last_synced_at', 'last_error'])]
class OneSignalSync extends Model
{
    protected $table = 'onesignal_syncs';

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'enabled' => 'boolean',
            'requested_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
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
}
