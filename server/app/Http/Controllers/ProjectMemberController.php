<?php

namespace App\Http\Controllers;

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProjectMemberController extends Controller
{
    /**
     * Adds an existing account, or invites a new person by email: the
     * invitation creates their account and gives them access to the project.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('configure', $project);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::enum(ProjectRole::class)],
        ]);
        $role = ProjectRole::from($data['role']);
        $this->ensureCanGrant($request->user(), $project, $role);

        $email = Str::lower($data['email']);

        if ($user = User::query()->where('email', $email)->first()) {
            $project->addMember($user, $role);
            Audit::log('project.member_added', $project, ['user' => $user->email, 'role' => $role->value]);

            return back()->with('status', __(':name now has access to the project.', ['name' => $user->name]));
        }

        Invitation::query()->pending()->where('email', $email)->delete();
        [$invitation, $token] = Invitation::issue($email, Role::User, $request->user(), $data['name'] ?? null, $project, $role);
        Audit::log('user.invited', $invitation, ['project' => $project->app_id, 'role' => $role->value]);

        $url = route('invitations.show', $token);
        $mailed = false;

        if (config('mail.default') !== 'log') {
            try {
                Notification::route('mail', $email)->notify(new InvitationNotification($invitation, $url));
                $mailed = true;
            } catch (Throwable $e) {
                Log::error('Unable to send invitation', ['error' => $e->getMessage()]);
            }
        }

        return back()
            ->with('status', $mailed
                ? __('Invitation sent to :email.', ['email' => $email])
                : __('Invitation created. Share the link below with :email.', ['email' => $email]))
            ->with('invitation_url', $url);
    }

    public function update(Request $request, Project $project, User $user): RedirectResponse
    {
        $this->authorize('configure', $project);

        $data = $request->validate(['role' => ['required', Rule::enum(ProjectRole::class)]]);
        $role = ProjectRole::from($data['role']);
        $current = $this->memberRole($project, $user);

        $this->ensureCanGrant($request->user(), $project, $role);
        $this->ensureCanGrant($request->user(), $project, $current);
        $this->ensureAnotherOwner($project, $user, $current, $role);

        $project->members()->updateExistingPivot($user->id, ['role' => $role->value]);
        Audit::log('project.member_updated', $project, ['user' => $user->email, 'role' => $role->value]);

        return back()->with('status', __('Role updated.'));
    }

    public function destroy(Request $request, Project $project, User $user): RedirectResponse
    {
        // Anyone may leave a project; removing others requires configuring it.
        if (! $request->user()->is($user)) {
            $this->authorize('configure', $project);
        }

        $current = $this->memberRole($project, $user);
        if (! $request->user()->is($user)) {
            $this->ensureCanGrant($request->user(), $project, $current);
        }
        $this->ensureAnotherOwner($project, $user, $current, null);

        $project->members()->detach($user->id);
        Audit::log('project.member_removed', $project, ['user' => $user->email]);

        return $request->user()->is($user) && ! $request->user()->isAdmin()
            ? redirect()->route('projects.index')->with('status', __('You left the project.'))
            : back()->with('status', __(':name no longer has access to the project.', ['name' => $user->name]));
    }

    protected function memberRole(Project $project, User $user): ProjectRole
    {
        $role = $project->members()->whereKey($user->id)->first()?->pivot?->role;
        abort_unless($role, 404);

        return ProjectRole::from($role);
    }

    /** Only owners give or take away the owner role. */
    protected function ensureCanGrant(User $actor, Project $project, ProjectRole $role): void
    {
        if ($role->isOwner() && ! $actor->roleIn($project)?->isOwner()) {
            throw ValidationException::withMessages(['role' => __('Only an owner can manage owners.')]);
        }
    }

    /** A project always keeps at least one owner. */
    protected function ensureAnotherOwner(Project $project, User $user, ProjectRole $current, ?ProjectRole $next): void
    {
        if (! $current->isOwner() || $next?->isOwner()) {
            return;
        }

        $owners = $project->members()->wherePivot('role', ProjectRole::Owner->value)->count();
        if ($owners <= 1) {
            throw ValidationException::withMessages(['role' => __('The project needs at least one owner. Make someone else owner first.')]);
        }
    }
}
