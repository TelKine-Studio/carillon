<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'locale', 'weekly_report'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'weekly_report' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->using(ProjectMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Projects this person can open: all of them for administrators.
     *
     * @return Builder<Project>
     */
    public function accessibleProjects(): Builder
    {
        return $this->isAdmin()
            ? Project::query()
            : Project::query()->whereHas('members', fn (Builder $query) => $query->whereKey($this->id));
    }

    /** Role in a project; administrators of the instance act as owners. */
    public function roleIn(Project $project): ?ProjectRole
    {
        if ($this->isAdmin()) {
            return ProjectRole::Owner;
        }

        $role = $project->members()->whereKey($this->id)->first()?->pivot?->role;

        return $role ? ProjectRole::tryFrom($role) : null;
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
