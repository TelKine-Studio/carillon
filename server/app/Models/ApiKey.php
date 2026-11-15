<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['project_id', 'name', 'prefix', 'key_hash', 'created_by', 'last_used_at'])]
#[Hidden(['key_hash'])]
class ApiKey extends Model
{
    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    /**
     * Creates a key; the plain value is returned once and never stored.
     *
     * @return array{0: ApiKey, 1: string}
     */
    public static function generate(Project $project, string $name, ?User $creator = null): array
    {
        $plain = 'crl_'.Str::random(44);

        $key = static::create([
            'project_id' => $project->id,
            'name' => $name,
            'prefix' => substr($plain, 0, 12),
            'key_hash' => hash('sha256', $plain),
            'created_by' => $creator?->id,
        ]);

        return [$key, $plain];
    }

    public static function findByPlain(string $plain): ?static
    {
        return static::query()->with('project')->where('key_hash', hash('sha256', $plain))->first();
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
