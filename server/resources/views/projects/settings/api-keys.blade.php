<x-projects.settings :project="$project" :section="$section">
    <x-ui.card :title="__('API keys')" :description="__('Your server sends notifications and manages subscribers with these keys. Keep them secret: never put them in an app.')" icon="key" :padding="false">
        @if ($canConfigure)
            <x-slot:actions>
                <x-ui.button type="button" size="sm" icon="plus" x-data x-on:click="$dispatch('open-modal', 'create-key')">{{ __('Create key') }}</x-ui.button>
            </x-slot:actions>
        @endif
        @if (session('plain_api_key'))
            <div class="border-b border-amber-200 bg-amber-50 p-5 sm:px-6 dark:border-amber-500/20 dark:bg-amber-500/10">
                <p class="mb-3 flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-200"><x-ui.icon name="alert" class="size-4" />{{ __('Copy this key now: it will not be shown again.') }}</p>
                <x-ui.code :label="__('API key')">{{ session('plain_api_key') }}</x-ui.code>
            </div>
        @endif

        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($apiKeys as $key)
                <li class="flex flex-wrap items-center gap-4 px-5 py-4 sm:px-6">
                    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-zinc-100 text-zinc-500 dark:bg-white/5"><x-ui.icon name="key" class="size-[18px]" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $key->name }}</p>
                        <p class="text-xs text-zinc-500">
                            <span class="font-mono">{{ $key->prefix }}…</span>
                            · {{ __('created :date', ['date' => $key->created_at->diffForHumans()]) }}@if ($key->creator) {{ __('by :name', ['name' => $key->creator->name]) }}@endif
                            · {{ $key->last_used_at ? __('last used :date', ['date' => $key->last_used_at->diffForHumans()]) : __('never used') }}
                        </p>
                    </div>
                    @if ($canConfigure)
                        <form method="POST" action="{{ route('projects.api-keys.destroy', [$project, $key]) }}" data-confirm-title="{{ __('Revoke this key?') }}" data-confirm="{{ __('Servers using it can no longer send notifications.') }}" data-confirm-action="{{ __('Revoke') }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button variant="ghost-danger" size="sm" icon="trash">{{ __('Revoke') }}</x-ui.button>
                        </form>
                    @endif
                </li>
            @empty
                @unless (session('plain_api_key'))
                    <li><x-ui.empty icon="key" :title="__('No API key yet')" :description="__('Create one per server or service, so you can revoke them separately.')" /></li>
                @endunless
            @endforelse
        </ul>

    </x-ui.card>

    @if ($canConfigure)
        <x-ui.modal name="create-key" :title="__('New API key')" :description="__('Create one per server or service, so you can revoke them separately.')" :open="$errors->has('name')">
            <form method="POST" action="{{ route('projects.api-keys.store', $project) }}">
                @csrf
                <x-ui.input name="name" :label="__('Name')" :placeholder="__('e.g. Production backend')" required maxlength="80" autofocus />
                <div class="mt-6 flex justify-end gap-3">
                    <x-ui.button type="button" variant="ghost" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button icon="plus">{{ __('Create key') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    <x-ui.card :title="__('Using a key')" icon="code">
        <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Send it in the Authorization header, like OneSignal:') }}</p>
        <x-ui.code label="curl">curl -X POST {{ rtrim(config('app.url'), '/') }}/api/v1/notifications \
  -H "Authorization: Key YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"app_id": "{{ $project->app_id }}", "included_segments": ["All"], "contents": {"en": "Hello!"}}'</x-ui.code>
    </x-ui.card>
</x-projects.settings>
