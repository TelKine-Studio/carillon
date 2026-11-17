{{-- People like the Users of OneSignal: totals of their devices, columns to choose. --}}
@php
    $columns = [
        'p_external' => __('External id'), 'p_channels' => __('Channels'), 'p_last' => __('Last session'),
        'p_first' => __('First session'), 'p_sessions' => __('Sessions'), 'p_usage' => __('Usage duration'),
        'p_timezone' => __('Timezone'), 'p_country' => __('Country'), 'p_language' => __('Language'), 'p_location' => __('Location'), 'p_tags' => __('Tags'),
    ];
    $sorts = ['p_last' => 'last_active', 'p_first' => 'first_active', 'p_sessions' => 'sessions', 'p_usage' => 'usage'];
    $duration = function (int $seconds): string {
        if ($seconds < 60) {
            return $seconds.' s';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60).' min';
        }

        return intdiv($seconds, 3600).' h '.str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT);
    };
    $languageName = fn (?string $code) => $code ? (class_exists(\Locale::class) ? ucfirst(\Locale::getDisplayLanguage($code, app()->getLocale())) : strtoupper($code)) : null;
    $date = fn ($at) => $at?->isoFormat('DD/MM/YY HH:mm');
    $filtered = $search || $platform || $state || $reason || $segment;
@endphp
<style>
    @foreach (array_keys($columns) as $key)
        [data-hidden~="{{ $key }}"] [data-col="{{ $key }}"] { display: none; }
    @endforeach
</style>
<div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-4 py-2 sm:px-5 dark:border-zinc-800">
    <p class="text-xs text-zinc-500 tabular-nums">{{ trans_choice(':count person|:count people', $results->total(), ['count' => number_format($results->total(), 0, ',', ' ')]) }}</p>
    <x-ui.dropdown width="w-64">
        <x-slot:trigger>
            <x-ui.button type="button" variant="ghost" size="sm" icon="columns">{{ __('Columns') }}</x-ui.button>
        </x-slot:trigger>
        <div class="max-h-80 overflow-y-auto p-1" x-on:click.stop>
            @foreach ($columns as $key => $columnLabel)
                <label class="flex cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5">
                    <input type="checkbox" class="size-3.5 rounded border-zinc-300 text-brand-600 focus:ring-brand-500/30 dark:border-zinc-600" :checked="shown('{{ $key }}')" x-on:change="toggle('{{ $key }}')">
                    {{ $columnLabel }}
                </label>
            @endforeach
        </div>
    </x-ui.dropdown>
</div>
@if ($results->isEmpty())
    <x-ui.empty icon="users" :title="$filtered ? __('Nobody matches') : __('No device yet')"
        :description="$filtered ? __('Try another search or filter.') : __('Devices appear here once your app uses the Carillon SDK with this App ID.')" />
@else
    <div class="relative overflow-x-auto" :data-hidden="hidden.join(' ')">
        <table class="w-full min-w-max text-sm">
            <thead>
                <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-medium whitespace-nowrap text-zinc-500 dark:border-zinc-800 dark:bg-white/[.03]">
                    <th class="sticky left-0 z-10 bg-zinc-50 px-4 py-2.5 font-medium sm:px-5 dark:bg-zinc-900">{{ __('User ID') }}</th>
                    @foreach ($columns as $key => $columnLabel)
                        <th data-col="{{ $key }}" class="px-3 py-2.5 font-medium">{!! isset($sorts[$key]) ? $sortLink($sorts[$key], $columnLabel) : e($columnLabel) !!}</th>
                    @endforeach
                    <th class="w-10 px-3 py-2.5"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($results as $person)
                    @php
                        $profile = route('projects.subscribers.show', [$project, $person]);
                        // Latest known position among the devices of the person.
                        $located = $person->subscriptions->whereNotNull('latitude')->sortByDesc('located_at')->first();
                    @endphp
                    <tr class="group whitespace-nowrap transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                        <td class="sticky left-0 z-10 bg-white px-4 py-2.5 group-hover:bg-zinc-50 sm:px-5 dark:bg-zinc-900 dark:group-hover:bg-zinc-800/60">
                            <span class="flex items-center gap-1">
                                <a href="{{ $profile }}" class="flex items-center gap-3">
                                    <x-ui.avatar :name="$person->external_id ?: '?'" size="sm" />
                                    <span class="font-mono text-xs text-zinc-700 hover:text-brand-700 dark:text-zinc-300 dark:hover:text-brand-300">{{ $person->id }}</span>
                                </a>
                                <x-ui.copy :value="$person->id" :label="__('Copy the user id')" />
                            </span>
                        </td>
                        <td data-col="p_external" class="px-3 py-2.5 text-xs">@if ($person->external_id)<span class="inline-flex items-center gap-0.5 font-mono text-zinc-900 dark:text-zinc-100">{{ $person->external_id }}<x-ui.copy :value="$person->external_id" class="-my-1" /></span>@else<span class="text-zinc-400">{{ __('Anonymous') }}</span>@endif</td>
                        <td data-col="p_channels" class="px-3 py-2.5">
                            <span class="flex items-center gap-1.5">
                                @foreach ($person->subscriptions as $device)
                                    @php
                                        $reachable = $device->enabled && ! $device->invalid_at;
                                        $icon = ['ios' => 'apple', 'android' => 'android', 'huawei' => 'android', 'amazon' => 'android', 'email' => 'mail', 'sms' => 'message', 'whatsapp' => 'chat'][$device->type] ?? 'globe-web';
                                    @endphp
                                    <span class="relative inline-flex size-7 items-center justify-center rounded-md bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300"
                                        title="{{ ($device->device_model ?: ucfirst($device->type)).' · '.($device->invalid_at ? __('Unreachable') : ($device->enabled ? __('Subscribed') : __('Unsubscribed'))) }}">
                                        <x-ui.icon :name="$icon" class="size-3.5" />
                                        <span @class(['absolute -top-0.5 -right-0.5 size-2 rounded-full ring-2 ring-white dark:ring-zinc-900', 'bg-emerald-500' => $reachable, 'bg-zinc-300 dark:bg-zinc-600' => ! $device->invalid_at && ! $reachable, 'bg-red-500' => (bool) $device->invalid_at])></span>
                                    </span>
                                @endforeach
                            </span>
                        </td>
                        <td data-col="p_last" class="px-3 py-2.5 text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $date($person->last_active_at) ?? '—' }}</td>
                        <td data-col="p_first" class="px-3 py-2.5 text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $date($person->created_at) }}</td>
                        <td data-col="p_sessions" class="px-3 py-2.5 text-right text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ number_format((int) $person->sessions_total, 0, ',', ' ') }}</td>
                        <td data-col="p_usage" class="px-3 py-2.5 text-right text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $person->usage_total ? $duration((int) $person->usage_total) : '—' }}</td>
                        <td data-col="p_timezone" class="px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $person->timezone ?? '—' }}</td>
                        <td data-col="p_country" class="px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $person->country ?? '—' }}</td>
                        <td data-col="p_language" class="px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $languageName($person->language) ?? '—' }}</td>
                        <td data-col="p_location" class="px-3 py-2.5 text-xs">@if ($located)<a href="https://www.openstreetmap.org/?mlat={{ $located->latitude }}&mlon={{ $located->longitude }}#map=13/{{ $located->latitude }}/{{ $located->longitude }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-mono text-zinc-700 hover:text-brand-700 dark:text-zinc-300 dark:hover:text-brand-300" title="{{ $located->located_at?->isoFormat('LLL') }}"><x-ui.icon name="map-pin" class="size-3" />{{ number_format($located->latitude, 2, '.', '') }}, {{ number_format($located->longitude, 2, '.', '') }}</a>@else<span class="text-zinc-400">—</span>@endif</td>
                        <td data-col="p_tags" class="px-3 py-2.5">
                            <span class="flex max-w-72 gap-1 overflow-hidden">
                                @forelse (array_slice($person->tags ?? [], 0, 3, true) as $tagKey => $tagValue)
                                    <span class="inline-flex shrink-0 rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-600 dark:bg-white/5 dark:text-zinc-400">{{ $tagKey }}={{ \Illuminate\Support\Str::limit((string) $tagValue, 18) }}</span>
                                @empty
                                    <span class="text-xs text-zinc-400">—</span>
                                @endforelse
                                @if (count($person->tags ?? []) > 3)<span class="text-[11px] text-zinc-400">+{{ count($person->tags) - 3 }}</span>@endif
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-right">
                    <x-ui.dropdown width="w-60">
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="ghost" size="icon" icon="more"><span class="sr-only">{{ __('Actions') }}</span></x-ui.button>
                        </x-slot:trigger>
                        <x-ui.dropdown-item :href="$profile" icon="eye">{{ __('View the profile') }}</x-ui.dropdown-item>
                        @can('send', $project)
                            <form method="POST" action="{{ route('projects.test-devices.store', $project) }}">
                                @csrf
                                <input type="hidden" name="identifier" value="{{ $person->id }}">
                                <x-ui.dropdown-item icon="smartphone">{{ __('Add as test devices') }}</x-ui.dropdown-item>
                            </form>
                        @endcan
                        <div x-data="copy(@js($person->id))"><x-ui.dropdown-item type="button" icon="copy" x-on:click="copy()"><span x-text="copied ? @js(__('Copied')) : @js(__('Copy the id'))">{{ __('Copy the id') }}</span></x-ui.dropdown-item></div>
                        <x-ui.dropdown-item :href="route('projects.subscribers.index', [$project, 'view' => 'devices', 'q' => $person->id])" icon="layers">{{ __('See their devices') }}</x-ui.dropdown-item>
                        @can('send', $project)
                            <div class="my-1 border-t border-zinc-100 dark:border-zinc-800"></div>
                            <form method="POST" action="{{ route('projects.subscribers.destroy', [$project, $person]) }}" data-confirm-title="{{ __('Delete this person?') }}" data-confirm="{{ __('Their devices stop receiving notifications until the app registers them again.') }}" data-confirm-action="{{ __('Delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.dropdown-item icon="trash" danger>{{ __('Delete the person') }}</x-ui.dropdown-item>
                            </form>
                        @endcan
                    </x-ui.dropdown>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
