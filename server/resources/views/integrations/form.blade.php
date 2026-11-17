@php
    $kind = $integration->kind;
    $exists = $integration->exists;
    $c = fn (string $key, $default = null) => $integration->setting($key, $default);
    $eventLabels = [
        'message.sent' => [__('Message sent'), __('With its totals: delivered, failed.')],
        'notification.received' => [__('Notification received'), __('Shown on a device.')],
        'notification.clicked' => [__('Notification clicked'), __('Opened, with the button pressed.')],
        'email.opened' => [__('Email opened'), __('Open pixel loaded.')],
        'email.clicked' => [__('Email clicked'), __('A tracked link opened.')],
        'subscription.created' => [__('New device'), __('A device registered.')],
        'subscription.unsubscribed' => [__('Device unsubscribed'), __('Turned off, or app uninstalled.')],
        'onesignal_sync.failed' => [__('OneSignal sync failed'), __('The nightly import failed.')],
        'integration.failed' => [__('Integration failed'), __('A SQL sync or an export failed.')],
    ];
    $secretLabels = [
        'posthog' => [__('Project API key'), 'phc_…'],
        'mixpanel' => [__('Project token'), ''],
        'amplitude' => [__('API key'), ''],
        'slack' => [__('Incoming webhook URL'), 'https://hooks.slack.com/services/…'],
        'discord' => [__('Webhook URL'), 'https://discord.com/api/webhooks/…'],
        'postgres' => [__('Password'), ''],
        'mysql' => [__('Password'), ''],
        's3' => [__('Secret access key'), ''],
    ];
    $stream = in_array($kind, \App\Models\Integration::STREAMS, true);
    $sql = in_array($kind, ['postgres', 'mysql'], true);
    $endpoint = route('sources.segment', $project->app_id);
    $statusColor = ! $integration->enabled ? ($integration->disabled_reason === 'failures' ? 'red' : 'zinc') : ($integration->last_error ? 'amber' : 'green');
    $statusLabel = ! $integration->enabled ? ($integration->disabled_reason === 'failures' ? __('Paused after failures') : __('Paused')) : ($integration->last_error ? __('Errors') : __('Active'));
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $defaultQuery = "SELECT id AS external_id, plan, city, language\nFROM users\nWHERE updated_at > ".($kind === 'mysql' ? 'NOW() - INTERVAL 2 DAY' : "now() - interval '2 days'");
@endphp
<x-layouts.app :title="$card['name'].' · '.__('Integrations').' · '.$project->name">
    <x-ui.page-header :title="$exists ? $integration->name : __('Connect :name', ['name' => $card['name']])" :description="$card['description']">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.integrations.index', [$project, 'tab' => $exists ? 'connections' : 'catalog']) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Integrations') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span>{{ $card['name'] }}</span>
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <div class="max-w-4xl min-w-0 space-y-6">
        @if ($exists)
            {{-- Status and actions --}}
            <section class="card p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        @include('integrations.partials.logo', ['card' => $card])
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 text-[15px] font-semibold text-zinc-900 dark:text-white">{{ $card['name'] }} <x-ui.badge :color="$statusColor" dot>{{ $statusLabel }}</x-ui.badge></p>
                            <p class="mt-0.5 text-sm text-zinc-500">
                                @if ($integration->last_success_at)
                                    {{ __('Last success :date', ['date' => $integration->last_success_at->diffForHumans()]) }}
                                @else
                                    {{ __('Nothing received or sent yet') }}
                                @endif
                                @if ($sql) · {{ $c('schedule') === 'hourly' ? __('every hour') : __('every night at 03:00') }}@endif
                                @if ($kind === 's3') · {{ __('every night at 04:15') }}@endif
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($kind !== 'segment')
                            <form method="POST" action="{{ route('projects.integrations.test', [$project, $integration]) }}">@csrf<x-ui.button variant="secondary" size="sm" icon="check">{{ $stream ? __('Send a test') : __('Test') }}</x-ui.button></form>
                        @endif
                        @if ($sql || $kind === 's3')
                            <form method="POST" action="{{ route('projects.integrations.run', [$project, $integration]) }}">@csrf<x-ui.button variant="secondary" size="sm" icon="play">{{ $sql ? __('Sync now') : __('Export yesterday') }}</x-ui.button></form>
                        @endif
                        <form method="POST" action="{{ route('projects.integrations.update', [$project, $integration]) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="enabled" value="{{ $integration->enabled ? 0 : 1 }}">
                            <x-ui.button variant="secondary" size="sm" :icon="$integration->enabled ? 'pause' : 'play'">{{ $integration->enabled ? __('Pause') : __('Resume') }}</x-ui.button>
                        </form>
                    </div>
                </div>

                @if ($integration->last_error)
                    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                        <p class="font-medium">{{ __('Last error') }} · {{ $integration->last_run_at?->diffForHumans() }}</p>
                        <p class="mt-0.5 font-mono text-xs break-words">{{ $integration->last_error }}</p>
                    </div>
                @endif

                @if ($integration->last_result)
                    @php $r = $integration->last_result; @endphp
                    <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-zinc-100 pt-4 text-sm sm:grid-cols-4 dark:border-zinc-800">
                        @foreach (match (true) {
                            $sql => [[__('Rows read'), $n($r['rows'] ?? 0)], [__('Users updated'), $n($r['updated'] ?? 0)], [__('Users created'), $n($r['created'] ?? 0)], [__('Unknown ids'), $n($r['unknown'] ?? 0)]],
                            $kind === 's3' => [[__('Day'), $r['day'] ?? '—'], [__('Rows'), $n($r['rows'] ?? 0)], [__('Size'), \Illuminate\Support\Number::fileSize($r['bytes'] ?? 0)]],
                            $kind === 'segment' => [[__('Users identified'), $n($r['identify'] ?? 0)], [__('Events'), $n($r['track'] ?? 0)], [__('Ignored'), $n($r['skipped'] ?? 0)]],
                            default => [],
                        } as [$label, $value])
                            <div class="min-w-0"><dt class="text-xs text-zinc-500">{{ $label }}</dt><dd class="mt-0.5 font-medium tabular-nums text-zinc-900 dark:text-white">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    @if ($kind === 's3' && isset($r['key']))<p class="mt-2 font-mono text-xs break-all text-zinc-500">{{ $r['key'] }}</p>@endif
                    @if ($kind === 'segment')<p class="mt-2 text-xs text-zinc-500">{{ __('Last request received.') }}</p>@endif
                @endif
            </section>

            @if ($kind === 'segment')
                <section class="card p-5 sm:p-6">
                    <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-white">{{ __('Connect your source') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('In Segment, add the destination Webhooks (Actions) with this URL; in RudderStack, the Webhook destination. Or point the HTTP API of a Segment library to it.') }}</p>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-medium text-zinc-500">{{ __('Endpoint URL') }}</dt>
                            <dd class="mt-1 flex items-center gap-1.5"><code class="min-w-0 font-mono text-[13px] break-all text-zinc-900 dark:text-zinc-100">{{ $endpoint }}</code><x-ui.copy :value="$endpoint" /></dd>
                        </div>
                        <div x-data="{ shown: false }">
                            <dt class="text-xs font-medium text-zinc-500">{{ __('Write key') }}</dt>
                            <dd class="mt-1 flex flex-wrap items-center gap-1.5">
                                <code class="min-w-0 font-mono text-[13px] break-all text-zinc-900 dark:text-zinc-100" x-text="shown ? {{ \Illuminate\Support\Js::from($integration->secret) }} : 'cws_••••••••••••••••'">cws_••••••••••••••••</code>
                                <button type="button" class="text-xs font-medium text-brand-700 hover:text-brand-800 dark:text-brand-300" x-on:click="shown = ! shown" x-text="shown ? @js(__('Hide')) : @js(__('Show'))">{{ __('Show') }}</button>
                                <x-ui.copy :value="$integration->secret" />
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Authentication: Basic, with the write key as user name and an empty password (as Segment), or the header Authorization: Bearer <key>.') }}</p>
                    <x-ui.code class="mt-3" :label="__('Example')">curl {{ $endpoint }}/track \
  -u {{ '$CARILLON_WRITE_KEY' }}: \
  -H 'Content-Type: application/json' \
  -d '{"type":"track","userId":"user-42","event":"Order Completed","properties":{"revenue":25}}'</x-ui.code>
                    <form method="POST" action="{{ route('projects.integrations.rotate', [$project, $integration]) }}" class="mt-4" data-confirm-title="{{ __('Generate a new write key?') }}" data-confirm="{{ __('The current key stops working at once.') }}" data-confirm-action="{{ __('Generate') }}">
                        @csrf
                        <x-ui.button variant="secondary" size="sm" icon="refresh">{{ __('New write key') }}</x-ui.button>
                    </form>
                </section>
            @endif
        @endif

        {{-- Settings --}}
        <form method="POST" action="{{ $exists ? route('projects.integrations.update', [$project, $integration]) : route('projects.integrations.store', $project) }}" class="card">
            @csrf
            @if ($exists)
                @method('PUT')
            @else
                <input type="hidden" name="card" value="{{ $card['key'] }}">
            @endif
            <div class="space-y-5 p-5 sm:p-6">
                <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-white">{{ __('Settings') }}</h2>
                <x-ui.input name="name" :label="__('Name')" :value="$integration->name" required maxlength="80" />

                @if (in_array($kind, ['posthog', 'mixpanel', 'amplitude'], true))
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.select name="region" :label="__('Region')" :value="$c('region', 'us')" :options="match ($kind) {
                            'posthog' => ['us' => __('United States (us.posthog.com)'), 'eu' => __('Europe (eu.posthog.com)'), 'custom' => __('Self-hosted')],
                            'mixpanel' => ['us' => __('United States'), 'eu' => __('Europe (EU residency)'), 'in' => __('India')],
                            default => ['us' => __('United States'), 'eu' => __('Europe (EU residency)')],
                        }" />
                        <x-ui.input name="prefix" :label="__('Prefix of event names')" :value="$c('prefix', 'Carillon')" :hint="__('“Carillon Notification clicked”. Empty: no prefix.')" optional />
                        @if ($kind === 'posthog')
                            <x-ui.input class="sm:col-span-2" name="host" type="url" :label="__('URL of your PostHog')" :value="$c('host')" placeholder="https://posthog.example.com" :hint="__('Only for the region Self-hosted.')" optional />
                        @endif
                    </div>
                @endif

                @if ($sql)
                    <div class="grid gap-5 sm:grid-cols-6">
                        <x-ui.input class="sm:col-span-4" name="host" :label="__('Host')" :value="$c('host')" required placeholder="db.example.com" />
                        <x-ui.input class="sm:col-span-2" name="port" type="number" :label="__('Port')" :value="$c('port', $kind === 'postgres' ? 5432 : 3306)" required />
                        <x-ui.input class="sm:col-span-2" name="database" :label="__('Database')" :value="$c('database')" required />
                        <x-ui.input class="sm:col-span-2" name="username" :label="__('User')" :value="$c('username')" required autocomplete="off" :hint="__('A read-only user is best.')" />
                        @if ($kind === 'postgres')
                            <x-ui.select class="sm:col-span-2" name="sslmode" :label="__('SSL')" :value="$c('sslmode', 'prefer')" :options="['prefer' => __('If available'), 'require' => __('Required'), 'verify-full' => __('Required and verified'), 'disable' => __('Disabled')]" />
                        @endif
                    </div>
                @endif

                @if ($kind === 's3')
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="bucket" :label="__('Bucket')" :value="$c('bucket')" required />
                        <x-ui.input name="region" :label="__('Region')" :value="$c('region', 'us-east-1')" required :hint="__('auto for Cloudflare R2.')" />
                        <x-ui.input name="endpoint" type="url" :label="__('Endpoint')" :value="$c('endpoint')" placeholder="https://<account>.r2.cloudflarestorage.com" :hint="__('Empty for Amazon S3.')" optional />
                        <x-ui.input name="prefix" :label="__('Folder')" :value="$c('prefix')" placeholder="exports/" optional />
                        <x-ui.input name="key_id" :label="__('Access key ID')" :value="$c('key_id')" required autocomplete="off" />
                    </div>
                @endif

                @if ($kind !== 'segment')
                    <x-ui.input name="secret" type="password" :label="$secretLabels[$kind][0]" autocomplete="new-password"
                        :placeholder="$exists ? __('Unchanged: leave empty to keep it') : $secretLabels[$kind][1]" :required="! $exists"
                        :hint="$kind === 'slack' ? __('Slack → Apps → Incoming Webhooks → Add New Webhook to Workspace.') : ($kind === 'discord' ? __('Channel settings → Integrations → Webhooks → New webhook → Copy URL.') : __('Stored encrypted.'))" />
                @endif

                @if ($kind === 's3')
                    <x-ui.toggle name="path_style" :label="__('Path-style URLs')" :description="__('bucket in the path rather than in the host name: needed by MinIO and most S3-compatible storages.')" :checked="(bool) $c('path_style', true)" />
                @endif

                @if ($sql)
                    <x-ui.textarea name="query" :label="__('Query')" :value="$c('query', $defaultQuery)" rows="6" style="font-family: var(--font-mono); font-size: 13px" required
                        :hint="__('One SELECT, run in a read-only session. The column :column finds the user; language, timezone and country are properties; every other column becomes a tag (empty value: tag removed).', ['column' => $c('id_column', 'external_id')])" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="id_column" :label="__('Column of the external id')" :value="$c('id_column', 'external_id')" required />
                        <x-ui.select name="schedule" :label="__('Frequency')" :value="$c('schedule', 'daily')" :options="['daily' => __('Every night at 03:00'), 'hourly' => __('Every hour')]" />
                    </div>
                    <x-ui.toggle name="create_users" :label="__('Create missing users')" :description="__('Off: only the users already known to Carillon (with a device) are updated.')" :checked="(bool) $c('create_users', false)" />
                @endif

                @if ($kind === 'segment')
                    <x-ui.toggle name="create_users" :label="__('Create users on identify')" :description="__('Off: identify only updates the users already known to Carillon.')" :checked="(bool) $c('create_users', true)" />
                    <x-ui.input name="skip_traits" :label="__('Traits not copied as tags')" :value="$c('skip_traits', 'email, phone, name, firstName, lastName')" :hint="__('Separated by commas. Other scalar traits become tags (address.city…).')" optional />
                @endif

                @if ($stream)
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Events') }}</legend>
                        @php $chosen = old('events', $integration->events ?? []); @endphp
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($eventLabels as $event => [$label, $text])
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-zinc-200 bg-white p-3 transition has-checked:border-brand-500 dark:border-zinc-800 dark:bg-zinc-900">
                                    <input type="checkbox" name="events[]" value="{{ $event }}" @checked(in_array($event, $chosen, true)) class="mt-0.5">
                                    <span class="min-w-0"><span class="block text-sm font-medium text-zinc-900 dark:text-white">{{ $label }}</span><span class="block text-xs text-zinc-500">{{ $text }}</span></span>
                                </label>
                            @endforeach
                        </div>
                        @error('events')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        @if (in_array($kind, ['posthog', 'mixpanel', 'amplitude'], true))
                            <p class="mt-2 text-xs text-zinc-500">{{ __('The user of each event is the external id of your app, else the Carillon id of the user.') }}</p>
                        @endif
                    </fieldset>
                @endif
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 px-5 py-4 sm:px-6 dark:border-zinc-800">
                <x-ui.button icon="check">{{ $exists ? __('Save') : __('Connect') }}</x-ui.button>
                @if (! $exists)
                    <x-ui.button :href="route('projects.integrations.index', [$project, 'tab' => 'catalog'])" variant="ghost">{{ __('Cancel') }}</x-ui.button>
                @endif
            </div>
        </form>

        @if ($exists)
            <form method="POST" action="{{ route('projects.integrations.destroy', [$project, $integration]) }}" data-confirm-title="{{ __('Delete this integration?') }}" data-confirm="{{ __('Its settings are erased; nothing is sent or synced any more.') }}" data-confirm-action="{{ __('Delete') }}" class="flex justify-end">
                @csrf
                @method('DELETE')
                <x-ui.button variant="ghost" size="sm" icon="trash" class="text-red-600 hover:text-red-700 dark:text-red-400">{{ __('Delete the integration') }}</x-ui.button>
            </form>
        @endif
    </div>
</x-layouts.app>
