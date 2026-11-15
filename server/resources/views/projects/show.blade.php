<x-layouts.app :title="$project->name">
    <div class="mb-8 flex flex-wrap items-center gap-4">
        <x-ui.project-icon :project="$project" size="lg" />
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $project->name }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ $role->label() }}</p>
        </div>
    </div>

    @php $done = collect($steps)->where('done', true)->count(); @endphp
    @if ($done < count($steps))
        <x-ui.card class="mb-6" :title="__('Get started')" :description="__(':done of :total steps completed', ['done' => $done, 'total' => count($steps)])" icon="sparkles">
            <ol class="grid gap-3 md:grid-cols-2">
                @foreach ($steps as $index => $step)
                    @php $tag = $step['href'] && ! $step['done'] ? 'a' : 'div'; @endphp
                    <li>
                        <{{ $tag }} @if ($tag === 'a') href="{{ $step['href'] }}" @endif @class(['flex h-full gap-3 rounded-2xl border p-4 transition', 'border-zinc-200 hover:border-brand-300 hover:bg-brand-50/40 dark:border-zinc-800 dark:hover:border-brand-500/40 dark:hover:bg-brand-500/5' => ! $step['done'], 'border-zinc-100 bg-zinc-50/60 dark:border-zinc-800/60 dark:bg-white/[.02]' => $step['done']])>
                            @if ($step['done'])
                                <span class="grid size-6 shrink-0 place-items-center rounded-md bg-emerald-500 text-white"><x-ui.icon name="check" class="size-4" /></span>
                            @else
                                <span class="grid size-6 shrink-0 place-items-center rounded-md bg-zinc-100 text-xs font-semibold text-zinc-600 dark:bg-white/10 dark:text-zinc-300">{{ $index + 1 }}</span>
                            @endif
                            <span>
                                <span @class(['block text-sm font-medium', 'text-zinc-400 line-through decoration-zinc-300 dark:decoration-zinc-600' => $step['done'], 'text-zinc-900 dark:text-white' => ! $step['done']])>{{ $step['title'] }}</span>
                                @unless ($step['done'])<span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $step['text'] }}</span>@endunless
                            </span>
                        </{{ $tag }}>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    @endif

    @php
        $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
        $ctr = $stats['delivered'] > 0 ? number_format($stats['clicked'] / $stats['delivered'] * 100, 1, ',', ' ').' %' : '—';
    @endphp
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat :label="__('Subscribed devices')" :value="$n($stats['devices'])" icon="smartphone" :href="route('projects.subscribers.index', $project)"
            :hint="collect(['ios' => 'iOS', 'android' => 'Android', 'web' => 'Web', 'email' => __('Email')])->filter(fn ($l, $k) => $stats['platforms'][$k] ?? 0)->map(fn ($l, $k) => $l.' '.$n($stats['platforms'][$k]))->implode(' · ') ?: null" />
        <x-ui.stat :label="__('New devices')" :value="$n(array_sum($stats['new']))" icon="users" :hint="__('Last 30 days')" :series="$stats['new']" />
        <x-ui.stat :label="__('Messages')" :value="$n($stats['messages'])" icon="send" :hint="__(':count delivered notifications', ['count' => $n($stats['delivered'])])" :href="route('projects.messages.index', $project)" />
        <x-ui.stat :label="__('Click rate')" :value="$ctr" icon="external" :hint="__(':count clicks in 30 days', ['count' => $n($stats['clicked'])])" />
    </div>

    @if ($latest->isNotEmpty())
        <x-ui.card class="mb-6" :title="__('Latest messages')" icon="send" :padding="false">
            <x-slot:actions>
                @can('send', $project)<x-ui.button size="sm" :href="route('projects.messages.create', $project)" icon="send">{{ __('New message') }}</x-ui.button>@endcan
            </x-slot:actions>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($latest as $message)
                    <li>
                        <a href="{{ route('projects.messages.show', [$project, $message]) }}" class="flex items-center gap-4 px-5 py-3 transition hover:bg-zinc-50/80 sm:px-6 dark:hover:bg-white/[.02]">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $message->label() }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ $message->audienceLabel() }} · {{ $message->created_at->diffForHumans() }}</p>
                            </div>
                            <span class="hidden text-sm tabular-nums text-zinc-600 sm:block dark:text-zinc-300">{{ $n($message->successful) }} / {{ $n($message->recipients) }}</span>
                            <x-messages.status :status="$message->status" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('App ID')" :description="__('Identifies this project in your apps (SDK) and in the API.')" icon="key">
            <x-ui.code :label="__('App ID')">{{ $project->app_id }}</x-ui.code>
            <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Server URL to give to the SDK:') }} <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ rtrim(config('app.url'), '/') }}</span></p>
        </x-ui.card>

        <x-ui.card :title="__('Platforms')" :description="__('Where this project can deliver notifications.')" icon="smartphone">
            <ul class="space-y-3">
                @foreach ([['apns', 'apple', __('iOS (Apple Push)')], ['fcm', 'android', __('Android (Firebase)')], ['webpush', 'globe-web', __('Web browsers')]] as [$provider, $icon, $label])
                    @php $credential = $project->credential($provider); @endphp
                    <li class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300"><x-ui.icon :name="$icon" class="size-[18px]" /></span>
                        <span class="flex-1 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $label }}</span>
                        @if ($credential?->isUsable())
                            <x-ui.badge color="green" dot>{{ __('Configured') }}</x-ui.badge>
                        @else
                            <x-ui.badge color="zinc">{{ __('Not configured') }}</x-ui.badge>
                        @endif
                    </li>
                @endforeach
            </ul>
            @can('configure', $project)
                <x-slot:footer><x-ui.button variant="secondary" :href="route('projects.settings', [$project, 'platforms'])" icon="settings">{{ __('Push keys') }}</x-ui.button></x-slot:footer>
            @endcan
        </x-ui.card>
    </div>
</x-layouts.app>
