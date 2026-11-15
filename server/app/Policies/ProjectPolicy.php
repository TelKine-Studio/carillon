<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Support\Settings;

class ProjectPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin() || app(Settings::class)->boolean('projects.users_can_create');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->roleIn($project) !== null;
    }

    /** Sends messages, edits segments, templates and subscribers. */
    public function send(User $user, Project $project): bool
    {
        return (bool) $user->roleIn($project)?->canSend();
    }

    /** Push keys, API keys, members and project details. */
    public function configure(User $user, Project $project): bool
    {
        return (bool) $user->roleIn($project)?->canConfigure();
    }

    public function delete(User $user, Project $project): bool
    {
        return (bool) $user->roleIn($project)?->isOwner();
    }
}
