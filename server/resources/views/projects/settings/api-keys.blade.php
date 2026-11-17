<x-projects.settings :project="$project" :section="$section">
    <x-ui.card :title="__('Identifiers')" :description="__('What your apps and your server need to reach this project.')" icon="link" :padding="false">
        <dl class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ([[__('App ID'), $project->app_id, __('In the SDK of your apps and in the API URLs.')], [__('Server URL'), url('/'), __('Where the SDK and your server send their requests.')]] as [$idLabel, $idValue, $idHint])
                <div x-data="copy" class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:gap-6 sm:px-6">
                    <dt class="sm:w-44 sm:shrink-0">
                        <span class="block text-sm font-medium text-zinc-900 dark:text-white">{{ $idLabel }}</span>
                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $idHint }}</span>
                    </dt>
                    <dd class="flex min-w-0 flex-1 items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 py-1 pr-1 pl-3 dark:border-zinc-800 dark:bg-white/[.03]">
                        <code x-ref="source" class="min-w-0 flex-1 truncate font-mono text-[13px] text-zinc-800 dark:text-zinc-200">{{ $idValue }}</code>
                        <button type="button" x-on:click="copy()" class="inline-flex shrink-0 items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-zinc-500 transition hover:bg-zinc-200/70 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white">
                            <x-ui.icon name="copy" class="size-3.5" x-show="!copied" />
                            <x-ui.icon name="check" class="size-3.5 text-emerald-500" x-show="copied" x-cloak />
                            <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                        </button>
                    </dd>
                </div>
            @endforeach
        </dl>
    </x-ui.card>
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
                        <p class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white"><span class="truncate">{{ $key->name }}</span><x-ui.badge :color="$key->scope === 'send' ? 'blue' : 'zinc'" class="shrink-0">{{ $key->scope === 'send' ? __('Sending only') : __('Full access') }}</x-ui.badge></p>
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
                <fieldset class="mt-5" x-data="{ scope: 'send' }">
                    <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Access') }}</legend>
                    <input type="hidden" name="scope" :value="scope">
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (['send' => [__('Sending only'), __('Send notifications, read their statistics, report events. Recommended for an app backend.')], 'full' => [__('Full access'), __('Also read, change and delete the audience (users API), manage templates.')]] as $value => [$label, $text])
                            <button type="button" x-on:click="scope = '{{ $value }}'" class="rounded-xl border p-3 text-left transition" :class="scope === '{{ $value }}' ? 'border-brand-500 bg-brand-50/50 ring-4 ring-brand-500/10 dark:bg-brand-500/10' : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-800'">
                                <span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ $label }}</span>
                                <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">{{ $text }}</span>
                            </button>
                        @endforeach
                    </div>
                </fieldset>
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
