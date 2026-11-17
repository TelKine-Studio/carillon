{{-- Devices like the Subscriptions of OneSignal; "test" adds the name of the test device and its actions. --}}
@php
    $columns = [
        'status' => __('State'), 'reason' => __('Status details'), 'unsubscribed' => __('Last unsubscribed'),
        'last_session' => __('Last session'), 'usage' => __('Usage duration'), 'sessions' => __('Sessions'),
        'first_session' => __('First session'), 'id' => __('Device id'), 'person' => __('Person'),
        'address' => __('Email or phone'), 'app_version' => __('App version'), 'timezone' => __('Timezone'),
        'country' => __('Country'), 'language' => __('Language'), 'location' => __('Location'), 'tags' => __('Tags'),
    ];
    $sorts = ['unsubscribed' => 'unsubscribed', 'last_session' => 'last_active', 'usage' => 'usage', 'sessions' => 'sessions', 'first_session' => 'first_active'];
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
@endphp
<style>
    @foreach (array_keys($columns) as $key)
        [data-hidden~="{{ $key }}"] [data-col="{{ $key }}"] { display: none; }
    @endforeach
</style>
<div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-4 py-2 sm:px-5 dark:border-zinc-800">
    <p class="text-xs text-zinc-500 tabular-nums">{{ trans_choice(':count device|:count devices', $results->total(), ['count' => number_format($results->total(), 0, ',', ' ')]) }}</p>
    <div class="flex items-center gap-2">
    @if ($test)
        @can('send', $project)
            <x-ui.button type="button" size="sm" icon="plus" x-on:click="$dispatch('open-modal', 'add-test-device')">{{ __('Add a test device') }}</x-ui.button>
        @endcan
    @endif
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
</div>
@php $filtered = $search || $platform || $state || $reason || $segment; @endphp
@if ($results->isEmpty())
    @if ($test)
        <x-ui.empty icon="smartphone" :title="$filtered ? __('Nobody matches') : __('No test device yet')"
            :description="$filtered ? __('Try another search or filter.') : __('They receive the tests sent from the composer, and the segment “Test Devices”. Add your own phone with its device id or the external id of your account in the app.')" />
    @else
        <x-ui.empty icon="users" :title="$filtered ? __('Nobody matches') : __('No device yet')"
            :description="$filtered ? __('Try another search or filter.') : __('Devices appear here once your app uses the Carillon SDK with this project’s App ID.')" />
    @endif
@else
    <div class="relative overflow-x-auto" :data-hidden="hidden.join(' ')">
        <table class="w-full min-w-max text-sm">
            <thead>
                <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-medium whitespace-nowrap text-zinc-500 dark:border-zinc-800 dark:bg-white/[.03]">
                    <th class="sticky left-0 z-10 bg-zinc-50 px-4 py-2.5 font-medium sm:px-5 dark:bg-zinc-900">{{ $test ? __('Name') : __('Device') }}</th>
                    @foreach ($columns as $key => $columnLabel)
                        <th data-col="{{ $key }}" class="px-3 py-2.5 font-medium">{!! isset($sorts[$key]) ? $sortLink($sorts[$key], $columnLabel) : e($columnLabel) !!}</th>
                    @endforeach
                    @if ($test)<th class="w-10 px-3 py-2.5"><span class="sr-only">{{ __('Actions') }}</span></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($results as $subscription)
                    @php
                        $person = $subscription->subscriber;
                        $profile = route('projects.subscribers.show', [$project, $person]);
                        $isAddress = in_array($subscription->type, ['email', 'sms', 'whatsapp'], true);
                    @endphp
                    <tr class="group whitespace-nowrap transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                        <td class="sticky left-0 z-10 bg-white px-4 py-2.5 group-hover:bg-zinc-50 sm:px-5 dark:bg-zinc-900 dark:group-hover:bg-zinc-800/60">
                            <a href="{{ $profile }}" class="flex max-w-60 min-w-0 items-center gap-3">
                                <x-audience.device :subscription="$subscription" />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-zinc-900 dark:text-white">{{ $test ? ($subscription->test_name ?: ($subscription->device_model ?: __('Unnamed device'))) : ($isAddress ? ucfirst($subscription->type) : ($subscription->device_model ?: ucfirst($subscription->type))) }}</span>
                                    <span class="block truncate text-xs text-zinc-500">{{ $test ? collect([$subscription->device_model, $subscription->device_os])->filter()->implode(' · ') : ($isAddress ? '' : ($subscription->device_os ?: '—')) }}</span>
                                </span>
                            </a>
                        </td>
                        <td data-col="status" class="px-3 py-2.5"><x-audience.state :subscription="$subscription" /></td>
                        <td data-col="reason" class="max-w-64 truncate px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400" title="{{ $subscription->statusDetails() }}">{{ $subscription->statusDetails() ?? '—' }}</td>
                        <td data-col="unsubscribed" class="px-3 py-2.5 text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $date($subscription->unsubscribed_at) ?? '—' }}</td>
                        <td data-col="last_session" class="px-3 py-2.5 text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $date($subscription->last_active_at) ?? '—' }}</td>
                        <td data-col="usage" class="px-3 py-2.5 text-right text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $subscription->usage_seconds ? $duration($subscription->usage_seconds) : '—' }}</td>
                        <td data-col="sessions" class="px-3 py-2.5 text-right text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ number_format($subscription->sessions, 0, ',', ' ') }}</td>
                        <td data-col="first_session" class="px-3 py-2.5 text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $date($subscription->created_at) }}</td>
                        <td data-col="id" class="px-3 py-2.5 font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $subscription->id }}</td>
                        <td data-col="person" class="max-w-52 truncate px-3 py-2.5 text-xs">@if ($person->external_id)<span class="font-mono text-zinc-800 dark:text-zinc-200">{{ $person->external_id }}</span>@else<span class="text-zinc-400">{{ __('Anonymous') }}</span>@endif</td>
                        <td data-col="address" class="max-w-56 truncate px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $isAddress ? $subscription->token : '—' }}</td>
                        <td data-col="app_version" class="px-3 py-2.5 text-xs text-zinc-600 tabular-nums dark:text-zinc-400">{{ $subscription->app_version ?? '—' }}</td>
                        <td data-col="timezone" class="px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $person->timezone ?? '—' }}</td>
                        <td data-col="country" class="px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $person->country ?? '—' }}</td>
                        <td data-col="language" class="px-3 py-2.5 text-xs text-zinc-600 dark:text-zinc-400">{{ $languageName($person->language) ?? '—' }}</td>
                        <td data-col="location" class="px-3 py-2.5 text-xs">@if ($subscription->latitude !== null)<a href="https://www.openstreetmap.org/?mlat={{ $subscription->latitude }}&mlon={{ $subscription->longitude }}#map=13/{{ $subscription->latitude }}/{{ $subscription->longitude }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-mono text-zinc-700 hover:text-brand-700 dark:text-zinc-300 dark:hover:text-brand-300" title="{{ $subscription->located_at?->isoFormat('LLL') }}"><x-ui.icon name="map-pin" class="size-3" />{{ number_format($subscription->latitude, 2, '.', '') }}, {{ number_format($subscription->longitude, 2, '.', '') }}</a>@else<span class="text-zinc-400">—</span>@endif</td>
            <td data-col="tags" class="px-3 py-2.5">
                            <span class="flex max-w-72 gap-1 overflow-hidden">
                                @forelse (array_slice($person->tags ?? [], 0, 3, true) as $key => $value)
                                    <span class="inline-flex shrink-0 rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[11px] text-zinc-600 dark:bg-white/5 dark:text-zinc-400">{{ $key }}={{ \Illuminate\Support\Str::limit((string) $value, 18) }}</span>
                                @empty
                                    <span class="text-xs text-zinc-400">—</span>
                                @endforelse
                                @if (count($person->tags ?? []) > 3)<span class="text-[11px] text-zinc-400">+{{ count($person->tags) - 3 }}</span>@endif
                            </span>
                        </td>
                        @if ($test)
                            <td class="px-3 py-2.5 text-right">
                                <x-ui.dropdown width="w-60">
                                    <x-slot:trigger>
                                        <x-ui.button type="button" variant="ghost" size="icon" icon="more"><span class="sr-only">{{ __('Actions') }}</span></x-ui.button>
                                    </x-slot:trigger>
                                    <x-ui.dropdown-item :href="$profile" icon="eye">{{ __('View the profile') }}</x-ui.dropdown-item>
                                    <div x-data="copy({{ \Illuminate\Support\Js::from($subscription->id) }})"><x-ui.dropdown-item type="button" icon="copy" x-on:click="copy()"><span x-text="copied ? @js(__('Copied')) : @js(__('Copy the id'))">{{ __('Copy the id') }}</span></x-ui.dropdown-item></div>
                                    @can('send', $project)
                                        <x-ui.dropdown-item type="button" icon="pencil"
                                            x-on:click="$dispatch('rename-test-device', { url: {{ \Illuminate\Support\Js::from(route('projects.test-devices.update', [$project, $subscription->id])) }}, name: {{ \Illuminate\Support\Js::from($subscription->test_name ?? '') }} })">{{ __('Rename') }}</x-ui.dropdown-item>
                                        <div class="my-1 border-t border-zinc-100 dark:border-zinc-800"></div>
                                        <form method="POST" action="{{ route('projects.test-devices.destroy', [$project, $subscription->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.dropdown-item icon="x" danger>{{ __('Stop using as a test device') }}</x-ui.dropdown-item>
                                        </form>
                                    @endcan
                                </x-ui.dropdown>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
