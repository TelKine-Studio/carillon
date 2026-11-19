<x-layouts.app :title="__('Live Activities').' · '.$project->name">
    <x-ui.page-header :title="__('Live Activities')" :description="__('Activities shown on the lock screen of iPhones and as Live Updates on Android, started and updated through the API.')">
        <x-ui.button variant="secondary" :href="route('projects.messages.index', $project)" icon="arrow-left">{{ __('Messages') }}</x-ui.button>
        @can('send', $project)
            <x-ui.button type="button" icon="play" x-data x-on:click="$dispatch('open-modal', 'live-start')">{{ __('Test an activity') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat :label="__('On screen now')" :value="number_format($open, 0, ',', ' ')" icon="activity" :hint="__('Started and not ended yet.')" />
        <x-ui.stat :label="__('Started in 24 h')" :value="number_format($today, 0, ',', ' ')" icon="zap" />
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Devices able to start') }}</p>
                <span class="inline-flex size-8 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400"><x-ui.icon name="apple" class="size-4" /></span>
            </div>
            @foreach ($types as $type => $devices)
                <p class="mt-3 flex items-baseline justify-between gap-3 text-sm"><span class="truncate font-mono text-xs text-zinc-600 dark:text-zinc-300">iOS · {{ $type }}</span><span class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($devices, 0, ',', ' ') }}</span></p>
            @endforeach
            <p class="mt-3 flex items-baseline justify-between gap-3 text-sm"><span class="truncate text-xs text-zinc-600 dark:text-zinc-300">Android</span><span class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ number_format($android, 0, ',', ' ') }}</span></p>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('iPhones on iOS 17.2+ whose app sent a push-to-start token; Android apps on the Carillon SDK 0.3+.') }}</p>
        </div>
    </div>

    {{-- What the app sent: the update token of an activity only comes through it. --}}
    <details class="card group mb-6 overflow-hidden" @if ($refused) open @endif>
        <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 sm:px-5">
            <x-ui.icon name="chevron-right" class="size-4 text-zinc-400 transition group-open:rotate-90" />
            <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Calls of the app (24 h)') }}</span>
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice(':count call|:count calls', $calls->count()) }}</span>
            @if ($refused)
                <x-ui.badge color="red">{{ trans_choice(':count refused|:count refused', $refused) }}</x-ui.badge>
            @endif
        </summary>
        <p class="border-t border-zinc-100 px-4 py-2 text-xs text-zinc-500 sm:px-5 dark:border-zinc-800 dark:text-zinc-400">{{ __('Start tokens, update tokens (enter) and ends (exit) sent by the SDK of the app. An activity started by push gets no update without its enter.') }}</p>
        @forelse ($calls as $call)
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 border-t border-zinc-100 px-4 py-2 text-xs sm:px-5 dark:border-zinc-800">
                <span class="tabular-nums text-zinc-500">{{ $call->created_at->isoFormat('D MMM HH:mm:ss') }}</span>
                <span class="font-mono font-medium text-zinc-800 dark:text-zinc-200">{{ $call->action }}</span>
                <x-ui.badge :color="$call->outcome === 'ok' ? 'green' : 'red'">{{ $outcomes[$call->outcome] ?? $call->outcome }}</x-ui.badge>
                @if ($call->activity_id)<span class="font-mono break-all text-zinc-700 dark:text-zinc-300">{{ $call->activity_id }}</span>@endif
                @if ($call->activity_type)<span class="font-mono text-zinc-500">{{ $call->activity_type }}</span>@endif
                <span class="text-zinc-500">{{ $call->subscription?->device_model ?? 'iOS' }}@if ($call->app_version) · {{ __('app :version', ['version' => $call->app_version]) }}@endif @if ($externalId = $call->subscription?->subscriber?->external_id) · {{ $externalId }}@endif</span>
                @unless ($call->subscription_id)
                    <span class="inline-flex items-center gap-1 font-mono break-all text-zinc-500">{{ $call->subscription_ref }}<x-ui.copy :value="$call->subscription_ref" /></span>
                @endunless
                @if ($call->detail)<span class="text-zinc-500">{{ __($call->detail) }}</span>@endif
            </div>
        @empty
            <p class="border-t border-zinc-100 px-4 py-4 text-sm text-zinc-500 sm:px-5 dark:border-zinc-800 dark:text-zinc-400">{{ __('No call from the app in the last 24 h.') }}</p>
        @endforelse
    </details>

    <form method="GET" class="relative mb-4 sm:w-72">
        <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-400" />
        <input type="search" name="q" autocomplete="off" value="{{ $search }}" placeholder="{{ __('Search an activity_id…') }}" class="form-control pl-9">
    </form>

    <div class="card overflow-hidden">
        @forelse ($activities as $activity)
            <div class="flex flex-col gap-2 border-t border-zinc-100 px-4 py-4 first:border-0 sm:px-5 lg:flex-row lg:items-center lg:gap-6 dark:border-zinc-800">
                <div class="min-w-0 flex-1">
                    <p class="flex min-w-0 flex-wrap items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white">
                        <span class="inline-flex min-w-0 items-center gap-0.5"><span class="font-mono break-all">{{ $activity->activity_id }}</span><x-ui.copy :value="$activity->activity_id" :label="__('Copy the id')" /></span>
                        <x-ui.badge :color="$colors[$activity->status] ?? 'zinc'" dot class="shrink-0">{{ $labels[$activity->status] ?? $activity->status }}</x-ui.badge>
                    </p>
                    <p class="mt-1 text-xs break-words text-zinc-500 dark:text-zinc-400">
                        <span class="font-mono">{{ $activity->activity_type }}</span>
                        · {{ $activity->subscription?->device_model ?? 'iOS' }}
                        @if ($version = $activity->subscription?->app_version) · {{ __('app :version', ['version' => $version]) }} @endif
                        @if ($activity->channel_id) · {{ __('iOS 18 channel') }}
                        @elseif ($activity->token_at) · {{ __('token received :time', ['time' => $activity->token_at->isoFormat('HH:mm:ss')]) }}
                        @elseif ($activity->isOpen()) · <span class="text-amber-700 dark:text-amber-400">{{ __('no update token from the app') }}</span> @endif
                        @if ($externalId = $activity->subscription?->subscriber?->external_id) · {{ $externalId }} @endif
                        @if ($activity->error) · <span class="text-red-600 dark:text-red-400">{{ $activity->error }}</span> @endif
                    </p>
                </div>
                @if ($activity->isOpen())
                    @can('send', $project)
                        <div class="flex shrink-0 gap-2" x-data>
                            <x-ui.button type="button" size="sm" variant="secondary" x-on:click="$dispatch('live-notify', { id: {{ \Illuminate\Support\Js::from($activity->activity_id) }}, event: 'update' })">{{ __('Update') }}</x-ui.button>
                            <x-ui.button type="button" size="sm" variant="secondary" x-on:click="$dispatch('live-notify', { id: {{ \Illuminate\Support\Js::from($activity->activity_id) }}, event: 'end' })">{{ __('End') }}</x-ui.button>
                        </div>
                    @endcan
                @endif
                <dl class="grid grid-cols-3 gap-4 text-xs lg:w-96">
                    <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Started') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 tabular-nums dark:text-zinc-100">{{ $activity->started_at?->isoFormat('D MMM HH:mm:ss') ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Updates') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 tabular-nums dark:text-zinc-100">{{ $activity->updates }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">{{ __('Ended') }}</dt><dd class="mt-0.5 font-medium text-zinc-900 tabular-nums dark:text-zinc-100">{{ $activity->ended_at?->isoFormat('D MMM HH:mm:ss') ?? '—' }}</dd></div>
                </dl>
            </div>
            @if ($activity->pushes)
                {{-- Answers of Apple / Google: what was sent, accepted or refused, with the ids to trace it. --}}
                <details class="group -mt-2 px-4 pb-3 text-xs sm:px-5">
                    <summary class="flex cursor-pointer list-none items-center gap-1.5 font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                        <x-ui.icon name="chevron-right" class="size-3.5 transition group-open:rotate-90" />{{ trans_choice(':count answer of the push service|:count answers of the push service', count($activity->pushes)) }}
                    </summary>
                    <ul class="mt-2 space-y-2">
                        @foreach ($activity->pushes as $push)
                            <li class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <span class="tabular-nums text-zinc-500">{{ \Illuminate\Support\Carbon::parse($push['at'])->isoFormat('D MMM HH:mm:ss') }}</span>
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $push['event'] }}</span>
                                <x-ui.badge :color="($push['result'] ?? '') === 'sent' ? 'green' : 'red'">{{ $push['status'] ?? '—' }}{{ isset($push['error']) ? ' · '.$push['error'] : '' }}</x-ui.badge>
                                @isset($push['environment'])<span class="text-zinc-500">{{ $push['environment'] }}</span>@endisset
                                @isset($push['channel'])<span class="font-mono text-zinc-500">{{ __('channel') }} {{ $push['channel'] }}</span>@endisset
                                @isset($push['token'])<span class="font-mono text-zinc-500">{{ __('token') }} {{ $push['token'] }}</span>@endisset
                                @isset($push['apns_id'])<span class="inline-flex items-center gap-1 font-mono text-zinc-500">apns-id {{ $push['apns_id'] }}<x-ui.copy :value="$push['apns_id']" /></span>@endisset
                                @isset($push['apns_unique_id'])<span class="inline-flex items-center gap-1 font-mono text-zinc-500">apns-unique-id {{ $push['apns_unique_id'] }}<x-ui.copy :value="$push['apns_unique_id']" /></span>@endisset
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        @empty
            <x-ui.empty icon="activity" :title="$search !== '' ? __('No activity matches') : __('No Live Activity yet')"
                :description="__('Start one from your server with POST /api/v1/apps/{app_id}/activities/activity/{type}: the guide is in the documentation (Channels → Live Activities).')" />
        @endforelse
    </div>

    @if ($activities->hasPages())
        <div class="mt-4">{{ $activities->links() }}</div>
    @endif

    @can('send', $project)
        <x-ui.modal name="live-start" :title="__('Test a Live Activity')" :description="__('Starts an activity on your own devices, to check the widget of your app (iOS) or the Live Update (Android).')" max-width="max-w-xl"
            :open="$errors->hasAny(['subscription_ids', 'activity_type', 'activity_id', 'state', 'attributes', 'android'])">
            <form method="POST" action="{{ route('projects.live-activities.start', $project) }}" class="space-y-4">
                @csrf
                <x-ui.textarea name="subscription_ids" :label="__('Device ids')" rows="2" :hint="__('One or more ids of devices (Audience). An iPhone needs the push-to-start token of the type.')" class="font-mono text-xs" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="activity_type" :label="__('Activity type')" :hint="__('Name of the ActivityAttributes struct.')" class="font-mono" required />
                    <x-ui.input name="activity_id" :label="__('Activity id')" :value="'test-'.now()->format('His')" class="font-mono" required />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="title" :label="__('Title')" :optional="true" />
                    <x-ui.input name="body" :label="__('Text')" :optional="true" />
                </div>
                <x-ui.textarea name="live" :label="__('Standard activity (JSON)')" rows="3" :optional="true" :hint="__('With the type CarillonActivityAttributes: shown by the ready-made widget on iOS and as a Live Update on Android, without the fields below.')" :placeholder="json_encode(['name' => 'My app', 'icon' => 'shippingbox.fill', 'color' => '#0F766E', 'title' => 'Order on its way', 'body' => 'Arrives in 20 min', 'status' => '1/3', 'step' => 1, 'steps' => 3], JSON_UNESCAPED_UNICODE)" class="font-mono text-xs" />
                <x-ui.textarea name="state" :label="__('Content state (JSON)')" rows="2" value="{}" class="font-mono text-xs" />
                <x-ui.textarea name="attributes" :label="__('Attributes (JSON)')" rows="2" value="{}" class="font-mono text-xs" />
                <x-ui.textarea name="android" :label="__('Android display (JSON)')" rows="2" :optional="true" :placeholder="json_encode(['progress' => 1, 'progress_max' => 3, 'segments' => 3])" class="font-mono text-xs" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="stale_minutes" type="number" min="1" max="480" :label="__('Stale after (minutes)')" :optional="true" />
                    <x-ui.input name="end_minutes" type="number" min="1" max="480" :label="__('Ended by Carillon after (minutes)')" :optional="true" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-modal', 'live-start')">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button icon="play">{{ __('Start') }}</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <div x-data="{ id: '', event: 'update' }" x-on:live-notify.window="id = $event.detail.id; event = $event.detail.event; $dispatch('open-modal', 'live-notify')">
            <x-ui.modal name="live-notify" :title="__('Update or end')" max-width="max-w-xl">
                <form method="POST" action="{{ route('projects.live-activities.notify', $project) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="activity_id" :value="id">
                    <input type="hidden" name="event" :value="event">
                    <p class="text-sm text-zinc-600 dark:text-zinc-300"><span x-text="event === 'end' ? @js(__('End')) : @js(__('Update'))"></span> · <span class="font-mono" x-text="id"></span></p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="update_title" :label="__('Title')" :optional="true" :hint="__('iOS: shown as an alert.')" />
                        <x-ui.input name="update_body" :label="__('Text')" :optional="true" />
                    </div>
                    <x-ui.textarea name="update_live" :label="__('Standard activity (JSON)')" rows="2" :optional="true" :placeholder="json_encode(['status' => '2/3', 'step' => 2], JSON_UNESCAPED_UNICODE)" class="font-mono text-xs" />
                    <x-ui.textarea name="update_state" :label="__('Content state (JSON)')" rows="2" :optional="true" class="font-mono text-xs" />
                    <x-ui.textarea name="update_android" :label="__('Android display (JSON)')" rows="2" :optional="true" class="font-mono text-xs" />
                    <div class="grid gap-4 sm:grid-cols-2" x-show="event === 'update'">
                        <x-ui.select name="priority" :label="__('Priority')" :options="['10' => __('10 — at once (Apple budget)'), '5' => __('5 — when convenient')]" value="10" />
                    </div>
                    <div x-show="event === 'end'">
                        <x-ui.input name="dismiss_minutes" type="number" min="0" max="240" :label="__('Removed from the screen after (minutes)')" :optional="true" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button type="button" variant="ghost" x-on:click="$dispatch('close-modal', 'live-notify')">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button icon="send"><span x-text="event === 'end' ? @js(__('End')) : @js(__('Update'))"></span></x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        </div>
    @endcan
</x-layouts.app>
