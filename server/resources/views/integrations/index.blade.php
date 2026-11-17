@php
    $byKind = collect($catalog)->filter(fn ($card) => ($card['kind'] ?? null) === $card['key'])->keyBy('kind');
    $directions = ['in' => __('Inbound'), 'out' => __('Outbound')];
    $connectedKinds = $integrations->pluck('kind')->unique()->all();
    $connections = $integrations->count() + $webhooks->count();
    $status = function ($item) {
        if (! $item->enabled) {
            return $item->disabled_reason === 'failures' ? ['red', __('Paused after failures')] : ['zinc', __('Paused')];
        }
        if (filled($item->last_error ?? null) || ($item->failures ?? 0) > 0) {
            return ['amber', __('Errors')];
        }

        return ['green', __('Active')];
    };
@endphp
<x-layouts.app :title="__('Integrations').' · '.$project->name">
    <x-ui.page-header :title="__('Integrations')" :description="__('Connect Carillon to your analytics, your data and your team: events sent out, users and tags coming in.')" />

    <div x-data="{ tab: @js($tab), direction: 'all', search: '' }">
        <nav class="scrollbar-none mb-6 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-800" aria-label="{{ __('Integrations') }}">
            @foreach (['connections' => [__('Connections'), $connections], 'catalog' => [__('Catalog'), count($catalog)]] as $key => [$tabLabel, $tabCount])
                <button type="button" x-on:click="tab = '{{ $key }}'; history.replaceState(null, '', '?tab={{ $key }}')" class="-mb-px inline-flex shrink-0 items-center gap-1.5 border-b-2 px-2.5 py-2.5 text-sm font-medium whitespace-nowrap transition"
                    :class="tab === '{{ $key }}' ? 'border-brand-600 text-zinc-900 dark:border-brand-400 dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'">
                    {{ $tabLabel }}<span class="min-w-5 rounded bg-zinc-100 px-1 text-center text-xs tabular-nums text-zinc-500 dark:bg-white/10 dark:text-zinc-400">{{ $tabCount }}</span>
                </button>
            @endforeach
        </nav>

        {{-- Connections --}}
        <div x-show="tab === 'connections'" @if ($tab !== 'connections') x-cloak @endif>
            @if ($connections === 0)
                <div class="card">
                    <x-ui.empty icon="link" :title="__('Nothing connected yet')" :description="__('Pick a tool in the catalog: analytics, database, chat or storage.')">
                        <x-ui.button type="button" icon="grid" x-on:click="tab = 'catalog'">{{ __('Browse the catalog') }}</x-ui.button>
                    </x-ui.empty>
                </div>
            @else
                <div class="card">
                    <div class="relative overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-medium whitespace-nowrap text-zinc-500 dark:border-zinc-800 dark:bg-white/[.03] dark:text-zinc-400">
                                    <th class="px-4 py-2.5 font-medium sm:px-5">{{ __('Name') }}</th>
                                    <th class="hidden px-3 py-2.5 font-medium md:table-cell">{{ __('Direction') }}</th>
                                    <th class="px-3 py-2.5 font-medium">{{ __('Status') }}</th>
                                    <th class="hidden px-3 py-2.5 font-medium sm:table-cell">{{ __('Last activity') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($integrations as $integration)
                                    @php $card = $byKind[$integration->kind] ?? ['monogram' => '', 'icon' => 'link', 'name' => $integration->kind]; [$color, $label] = $status($integration); @endphp
                                    <tr class="relative transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                                        <td class="px-4 py-3 sm:px-5">
                                            <span class="flex min-w-0 items-center gap-3">
                                                @include('integrations.partials.logo', ['card' => $card, 'size' => 'size-8'])
                                                <span class="min-w-0">
                                                    @can('configure', $project)
                                                        <a href="{{ route('projects.integrations.show', [$project, $integration]) }}" class="block font-medium break-words text-zinc-900 after:absolute after:inset-0 hover:text-brand-700 dark:text-zinc-100 dark:hover:text-brand-300">{{ $integration->name }}</a>
                                                    @else
                                                        <span class="block font-medium break-words text-zinc-900 dark:text-zinc-100">{{ $integration->name }}</span>
                                                    @endcan
                                                    <span class="block text-xs text-zinc-500">{{ $card['name'] }}</span>
                                                </span>
                                            </span>
                                        </td>
                                        <td class="hidden px-3 py-3 whitespace-nowrap text-zinc-600 md:table-cell dark:text-zinc-300">{{ $directions[$integration->direction()] }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap"><x-ui.badge :color="$color" dot>{{ $label }}</x-ui.badge></td>
                                        <td class="hidden px-3 py-3 whitespace-nowrap text-zinc-500 sm:table-cell">{{ $integration->last_run_at?->diffForHumans() ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                @foreach ($webhooks as $webhook)
                                    @php [$color, $label] = $status($webhook); @endphp
                                    <tr class="relative transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                                        <td class="px-4 py-3 sm:px-5">
                                            <span class="flex min-w-0 items-center gap-3">
                                                @include('integrations.partials.logo', ['card' => ['monogram' => '', 'icon' => 'link'], 'size' => 'size-8'])
                                                <span class="min-w-0">
                                                    <a href="{{ route('projects.settings', [$project, 'webhooks']) }}" class="block font-mono text-[13px] break-all text-zinc-900 after:absolute after:inset-0 hover:text-brand-700 dark:text-zinc-100 dark:hover:text-brand-300">{{ $webhook->url }}</a>
                                                    <span class="block text-xs text-zinc-500">{{ __('Webhook') }} · {{ trans_choice(':count event|:count events', count($webhook->events ?? [])) }}</span>
                                                </span>
                                            </span>
                                        </td>
                                        <td class="hidden px-3 py-3 whitespace-nowrap text-zinc-600 md:table-cell dark:text-zinc-300">{{ $directions['out'] }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap"><x-ui.badge :color="$color" dot>{{ $label }}</x-ui.badge></td>
                                        <td class="hidden px-3 py-3 whitespace-nowrap text-zinc-500 sm:table-cell">{{ $webhook->last_delivery_at?->diffForHumans() ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- Catalog --}}
        <div x-show="tab === 'catalog'" @if ($tab !== 'catalog') x-cloak @endif>
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <label class="relative block w-full sm:max-w-xs">
                    <span class="sr-only">{{ __('Search integrations') }}</span>
                    <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-zinc-400" />
                    <input type="search" x-model="search" placeholder="{{ __('Search integrations…') }}" class="form-control pl-8">
                </label>
                <div class="inline-flex h-8 shrink-0 self-start rounded-md border border-zinc-200 bg-white p-0.5 text-sm sm:self-auto dark:border-zinc-800 dark:bg-zinc-900" role="group" aria-label="{{ __('Direction') }}">
                    @foreach (['all' => __('All'), 'in' => __('Inbound'), 'out' => __('Outbound')] as $key => $label)
                        <button type="button" x-on:click="direction = '{{ $key }}'" :aria-pressed="direction === '{{ $key }}'" class="rounded px-2.5 font-medium transition"
                            :class="direction === '{{ $key }}' ? 'bg-zinc-100 text-zinc-900 dark:bg-white/10 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($catalog as $card)
                    @php
                        $connected = isset($card['kind']) ? in_array($card['kind'], $connectedKinds, true) : ($card['connected'] ?? false);
                        $href = isset($card['kind']) ? route('projects.integrations.create', [$project, $card['key']]) : $card['url'];
                        $haystack = mb_strtolower($card['name'].' '.$card['description'].' '.$categories[$card['category']]);
                    @endphp
                    <article class="card flex flex-col p-5"
                        x-show="(direction === 'all' || {{ \Illuminate\Support\Js::from($card['directions']) }}.includes(direction)) && (search === '' || {{ \Illuminate\Support\Js::from($haystack) }}.includes(search.toLowerCase()))">
                        <div class="flex items-start justify-between gap-3">
                            @include('integrations.partials.logo', ['card' => $card])
                            <span class="flex flex-wrap justify-end gap-1">
                                @foreach ($card['directions'] as $direction)<x-ui.badge>{{ $directions[$direction] }}</x-ui.badge>@endforeach
                            </span>
                        </div>
                        <p class="mt-4 text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $categories[$card['category']] }}</p>
                        <h3 class="mt-0.5 text-[15px] font-semibold text-zinc-900 dark:text-white">{{ $card['name'] }}</h3>
                        <p class="mt-1.5 flex-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $card['description'] }}</p>
                        <div class="mt-4 flex items-center justify-between gap-3">
                            @if ($connected)
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-400"><x-ui.icon name="check-circle" class="size-4" />{{ __('Connected') }}</span>
                            @else
                                <span></span>
                            @endif
                            @if (isset($card['kind']))
                                @can('configure', $project)
                                    <x-ui.button :href="$href" variant="secondary" size="sm" icon="plus">{{ __('Connect') }}</x-ui.button>
                                @endcan
                            @elseif (! isset($card['can']) || auth()->user()->can($card['can'], $project))
                                <x-ui.button :href="$href" variant="secondary" size="sm">{{ __('Open') }}</x-ui.button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <p class="mt-6 text-center text-sm text-zinc-500" x-show="search !== ''" x-cloak>{{ __('Not in the list? Webhooks and the REST API connect Carillon to any tool.') }}</p>
        </div>
    </div>
</x-layouts.app>
