<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['project_id', 'name', 'scope', 'prefix', 'key_hash', 'created_by', 'last_used_at'])]
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
    public const SCOPES = ['full', 'send'];

    /** What a "send" key may call: a leak lets nobody read or delete the audience. */
    public function allows(string $method, string $uri): bool
    {
        if ($this->scope !== 'send') {
            return true;
        }

        return str_starts_with($uri, 'api/v1/notifications')
            || ($method === 'GET' && str_starts_with($uri, 'api/v1/templates'))
            || preg_match('#^api/v1/apps/[^/]+/(custom_events|stats|activities/activity/[^/]+|live_activities/[^/]+/notifications)$#', $uri) === 1;
    }

    public static function generate(Project $project, string $name, ?User $creator = null, string $scope = 'full'): array
    {
        $plain = 'crl_'.Str::random(44);

        $key = static::create([
            'project_id' => $project->id,
            'name' => $name,
            'scope' => in_array($scope, self::SCOPES, true) ? $scope : 'full',
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
