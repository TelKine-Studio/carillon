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
                        <code x-ref="source" class="min-w-0 flex-1 font-mono text-[13px] break-all text-zinc-800 dark:text-zinc-200">{{ $idValue }}</code>
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
    @if ($canConfigure)
        @php $identityOn = $project->verifiesIdentity(); $identitySecret = $project->identitySecret(); @endphp
        <x-ui.card :title="__('Identity verification')" :description="__('Without it, any device that knows the App ID can log in as any external id and receive the notifications of that user. With it, your server signs each external id and the SDK sends that signature.')" icon="shield">
            <div class="space-y-4">
                @if (! $identitySecret)
                    <form method="POST" action="{{ route('projects.identity.update', $project) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="rotate" value="1">
                        <p class="mb-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('First generate the secret and update your server and your apps; then turn the verification on.') }}</p>
                        <x-ui.button size="sm" icon="key">{{ __('Generate the identity secret') }}</x-ui.button>
                    </form>
                @else
                <form method="POST" action="{{ route('projects.identity.update', $project) }}" class="flex flex-wrap items-center justify-between gap-3">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="identity_verification" value="{{ $identityOn ? 0 : 1 }}">
                        <span class="inline-flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                            <span @class(['size-2 rounded-full', 'bg-emerald-500' => $identityOn, 'bg-zinc-300 dark:bg-zinc-600' => ! $identityOn])></span>
                            {{ $identityOn ? __('On: logins without a valid hash are refused') : __('Off') }}
                        </span>
                        <x-ui.button size="sm" :variant="$identityOn ? 'secondary' : 'primary'" :icon="$identityOn ? 'pause' : 'shield'">{{ $identityOn ? __('Turn off') : __('Turn on') }}</x-ui.button>
                    </form>
                    @php
                        $checks = \App\Audience\IdentityChecks::summary($project);
                        $checked = $checks['valid'] + $checks['invalid'] + $checks['missing'];
                    @endphp
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Logins of the last 7 days') }}</p>
                        <p class="mt-0.5 text-xs text-zinc-500">{{ __('Counted even while the verification is off: turn it on when almost every login sends a valid hash.') }}</p>
                        <dl class="mt-3 grid grid-cols-3 gap-3 text-sm">
                            @foreach ([['valid', __('Valid hash'), 'bg-emerald-500'], ['missing', __('Without hash'), 'bg-zinc-400'], ['invalid', __('Wrong hash'), 'bg-red-500']] as [$key, $label, $dot])
                                <div class="min-w-0">
                                    <dt class="flex items-center gap-1.5 text-xs text-zinc-500"><span class="size-2 rounded-full {{ $dot }}"></span>{{ $label }}</dt>
                                    <dd class="mt-0.5 font-medium tabular-nums text-zinc-900 dark:text-white">{{ number_format($checks[$key], 0, ',', ' ') }}@if ($checked)<span class="ml-1 text-xs font-normal text-zinc-500">{{ round($checks[$key] / $checked * 100) }} %</span>@endif</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                    <div>
                        <p class="mb-1.5 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Identity secret') }}</p>
                        <div class="flex flex-wrap items-center gap-1.5" x-data="{ shown: false }">
                            <code class="min-w-0 font-mono text-[13px] break-all text-zinc-900 dark:text-zinc-100" x-text="shown ? {{ \Illuminate\Support\Js::from($identitySecret) }} : '••••••••••••••••••••••••'">••••••••••••••••••••••••</code>
                            <button type="button" class="text-xs font-medium text-brand-700 hover:text-brand-800 dark:text-brand-300" x-on:click="shown = ! shown" x-text="shown ? @js(__('Hide')) : @js(__('Show'))">{{ __('Show') }}</button>
                            <x-ui.copy :value="$identitySecret" />
                        </div>
                        <p class="mt-1.5 text-xs text-zinc-500">{{ __('Keep it on your server only. Never put it in an app.') }}</p>
                    </div>
                    <x-ui.code :label="__('On your server')">identity_hash = hex(HMAC-SHA256(key = identity secret, message = external_id))
// PHP:  hash_hmac('sha256', $externalId, $secret)
// Node: crypto.createHmac('sha256', secret).update(externalId).digest('hex')</x-ui.code>
                    <x-ui.code :label="__('In the app')">Carillon.login('user-42', identityHash: hashFromYourServer);   // Flutter
Carillon.login('user-42', { identityHash: hashFromYourServer });  // Web</x-ui.code>
                    <form method="POST" action="{{ route('projects.identity.update', $project) }}" data-confirm-title="{{ __('Generate a new identity secret?') }}" data-confirm="{{ __('Hashes made with the current secret stop working at once.') }}" data-confirm-action="{{ __('Generate') }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="rotate" value="1">
                        <x-ui.button variant="secondary" size="sm" icon="refresh">{{ __('New secret') }}</x-ui.button>
                    </form>
                @endif
            </div>
        </x-ui.card>
    @endif
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
