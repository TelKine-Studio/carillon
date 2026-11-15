<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'icon_path', 'settings', 'created_by'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const PROVIDERS = ['apns', 'fcm', 'webpush'];

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->app_id ??= (string) Str::uuid();
        });

        static::deleting(function (Project $project) {
            if ($project->icon_path) {
                Storage::disk('local')->delete($project->icon_path);
            }
        });
    }

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'app_id';
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->using(ProjectMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<ApiKey, $this> */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /** @return HasMany<PushCredential, $this> */
    public function credentials(): HasMany
    {
        return $this->hasMany(PushCredential::class);
    }

    public function credential(string $provider): ?PushCredential
    {
        return $this->credentials->firstWhere('provider', $provider);
    }

    /** Platforms this project can deliver to. */
    public function configuredProviders(): array
    {
        return $this->credentials->filter->isUsable()->pluck('provider')->all();
    }

    public function addMember(User $user, ProjectRole $role): void
    {
        $this->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
