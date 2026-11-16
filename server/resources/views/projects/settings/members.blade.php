@php $roles = collect(\App\Enums\ProjectRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all(); @endphp
<x-projects.settings :project="$project" :section="$section">
    <x-ui.card :title="__('Members')" :description="__('People working on this project. Administrators of the instance have access to every project.')" icon="users" :padding="false">
        @if ($canConfigure)
            <x-slot:actions>
                <x-ui.button type="button" size="sm" icon="user-plus" x-data x-on:click="$dispatch('open-modal', 'add-member')">{{ __('Add someone') }}</x-ui.button>
            </x-slot:actions>
        @endif
        @if (session('invitation_url'))
            <div class="px-5 pb-4 sm:px-6"><x-ui.code :label="__('Invitation link')">{{ session('invitation_url') }}</x-ui.code></div>
        @endif
        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($members as $member)
                @php $memberRole = \App\Enums\ProjectRole::from($member->pivot->role); @endphp
                <li class="flex flex-wrap items-center gap-4 px-5 py-4 sm:px-6">
                    <x-ui.avatar :user="$member" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $member->name }} @if ($member->is(auth()->user()))<span class="text-xs font-normal text-zinc-400">({{ __('you') }})</span>@endif</p>
                        <p class="truncate text-xs text-zinc-500">{{ $member->email }}</p>
                    </div>
                    @if ($canConfigure)
                        <form method="POST" action="{{ route('projects.members.update', [$project, $member]) }}">
                            @csrf
                                @method('PUT')
                                <x-ui.select name="role" size="sm" :id="'role-'.$member->id" :options="$roles" :value="$memberRole->value" onchange="this.form.submit()" />
                            </form>
                        @else
                            <x-ui.badge :color="$memberRole->isOwner() ? 'brand' : 'zinc'">{{ $memberRole->label() }}</x-ui.badge>
                        @endif
                        @if ($canConfigure || $member->is(auth()->user()))
                            <form method="POST" action="{{ route('projects.members.destroy', [$project, $member]) }}" data-confirm-title="{{ $member->is(auth()->user()) ? __('Leave this project?') : __('Remove :name?', ['name' => $member->name]) }}" data-confirm="{{ __('Access to the project is removed right away.') }}" data-confirm-action="{{ $member->is(auth()->user()) ? __('Leave') : __('Remove') }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="ghost-danger" size="icon" icon="x" class="size-8"><span class="sr-only">{{ __('Remove') }}</span></x-ui.button>
                            </form>
                        @endif
                    </li>
                @endforeach
                @foreach ($invitations as $invitation)
                    <li class="flex flex-wrap items-center gap-4 px-5 py-4 sm:px-6">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-zinc-100 text-zinc-400 dark:bg-white/5"><x-ui.icon name="mail" class="size-4" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $invitation->name ?? $invitation->email }}</p>
                            <p class="truncate text-xs text-zinc-500">{{ $invitation->email }} · {{ __('invited, expires :date', ['date' => $invitation->expires_at->diffForHumans()]) }}</p>
                        </div>
                        <x-ui.badge color="amber">{{ \App\Enums\ProjectRole::tryFrom((string) $invitation->project_role)?->label() }}</x-ui.badge>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>

        @if ($canConfigure)
            <x-ui.modal name="add-member" max-width="max-w-2xl" :title="__('Add someone')" :description="__('An existing account gets access right away; a new address receives an invitation to create its account.')"
                :open="$errors->hasAny(['email', 'name', 'role'])">
                <form method="POST" action="{{ route('projects.members.store', $project) }}">
                    @csrf
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="email" type="email" :label="__('Email')" required list="known-users" />
                    <x-ui.input name="name" :label="__('Name')" :hint="__('For a new person.')" optional />
                </div>
                <datalist id="known-users">
                    @foreach ($candidates as $candidate)<option value="{{ $candidate->email }}">{{ $candidate->name }}</option>@endforeach
                </datalist>
                <fieldset class="mt-6">
                    <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Role') }}</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach (\App\Enums\ProjectRole::cases() as $option)
                            @continue($option->isOwner() && ! $role->isOwner())
                            <label class="flex cursor-pointer gap-3 rounded-2xl border border-zinc-200 p-4 transition has-checked:border-brand-500 has-checked:bg-brand-50/50 has-checked:ring-4 has-checked:ring-brand-500/10 dark:border-zinc-800 dark:has-checked:bg-brand-500/5">
                                <input type="radio" name="role" value="{{ $option->value }}" class="mt-1" @checked(old('role', 'editor') === $option->value)>
                                <span>
                                    <span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ $option->label() }}</span>
                                    <span class="mt-0.5 block text-sm text-zinc-500 dark:text-zinc-400">{{ $option->description() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="mt-6 flex justify-end gap-3">
                    <x-ui.button type="button" variant="ghost" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button icon="plus">{{ __('Add') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</x-projects.settings>
