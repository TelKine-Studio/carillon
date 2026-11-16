<x-layouts.app :title="$user->name">
    <nav class="mb-4 flex items-center gap-1.5 text-sm text-zinc-500">
        <a href="{{ route('admin.users.index') }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Users') }}</a>
        <x-ui.icon name="chevron-right" class="size-3.5" /><span class="truncate">{{ $user->name }}</span>
    </nav>
    <div class="mb-8 flex flex-wrap items-center gap-5">
        <x-ui.avatar :user="$user" size="lg" class="size-16 text-xl" />
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $user->name }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ $user->email }} · {{ __('Member since :date', ['date' => $user->created_at->isoFormat('LL')]) }}</p>
        </div>
    </div>

    <div class="max-w-3xl space-y-6">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            <x-settings.section :title="__('Account')" icon="user" :fields="['name', 'email', 'role', 'is_active']" :save-label="__('Save changes')">
                <x-slot:summary>
                    <x-settings.summary>
                        <x-settings.value :label="__('Full name')" :value="$user->name" />
                        <x-settings.value :label="__('Email')" :value="$user->email" />
                        <x-settings.value :label="__('Role')" :value="$user->role->label()" />
                        <x-settings.value :label="__('Status')" :on="$user->is_active" :value="__('Active')" :empty="__('Suspended')" />
                    </x-settings.summary>
                </x-slot:summary>
                <div class="space-y-6">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="name" :label="__('Full name')" :value="$user->name" required />
                        <x-ui.input name="email" type="email" :label="__('Email')" :value="$user->email" required />
                    </div>
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Role') }}</legend>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ([
                                'user' => ['icon' => 'user', 'text' => __('Works on the projects they are added to, with the role given there.')],
                                'admin' => ['icon' => 'shield', 'text' => __('Full access: users, settings and every project.')],
                            ] as $value => $option)
                                <label class="flex cursor-pointer gap-3 rounded-2xl border border-zinc-200 p-4 transition has-checked:border-brand-500 has-checked:bg-brand-50/50 has-checked:ring-4 has-checked:ring-brand-500/10 dark:border-zinc-800 dark:has-checked:bg-brand-500/5">
                                    <input type="radio" name="role" value="{{ $value }}" class="mt-1 accent-brand-600" @checked(old('role', $user->role->value) === $value)>
                                    <span>
                                        <span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ \App\Enums\Role::from($value)->label() }}</span>
                                        <span class="mt-0.5 block text-sm text-zinc-500 dark:text-zinc-400">{{ $option['text'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('role')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </fieldset>
                    <div class="border-t border-zinc-100 pt-6 dark:border-zinc-800">
                        <x-ui.toggle name="is_active" :checked="$user->is_active" :label="__('Active account')" :description="__('Disabled users cannot log in and lose access to their projects.')" />
                    </div>
                </div>
            </x-settings.section>
        </form>

        @unless ($user->is(auth()->user()))
            <section class="rounded-2xl border border-red-200 bg-red-50/40 p-5 sm:p-6 dark:border-red-500/20 dark:bg-red-500/5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-red-900 dark:text-red-200">{{ __('Delete this user') }}</h2>
                        <p class="mt-1 text-sm text-red-800/80 dark:text-red-200/70">{{ __('The account is deleted permanently. Its projects are kept.') }}</p>
                    </div>
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm-title="{{ __('Delete :name permanently?', ['name' => $user->name]) }}" data-confirm="{{ __('This cannot be undone.') }}" data-confirm-action="{{ __('Delete user') }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="danger" icon="trash">{{ __('Delete user') }}</x-ui.button>
                    </form>
                </div>
            </section>
        @endunless
    </div>
</x-layouts.app>
