@php
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $layouts = ['banner' => __('Banner'), 'modal' => __('Pop-up'), 'fullscreen' => __('Full screen')];
@endphp
<x-layouts.app :title="__('In-app messages').' · '.$project->name">
    <x-ui.page-header :title="__('In-app messages')" :description="__('Banners and pop-ups shown inside your app while it is open: no permission needed, they reach every user.')">
        @can('send', $project)
            <x-ui.button :href="route('projects.in-app.create', $project)" icon="plus">{{ __('New in-app message') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200">
        <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0" />
        <p>{{ __('Displayed by the Carillon SDK (Flutter and web): devices still running the OneSignal SDK do not show them.') }}</p>
    </div>

    <div class="card">
        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($messages as $message)
                @php
                    [$color, $label] = match ($message->status) {
                        'active' => ['green', __('Active')],
                        'paused' => ['amber', __('Paused')],
                        default => ['zinc', __('Draft')],
                    };
                @endphp
                <li class="flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4 sm:px-6">
                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300"><x-ui.icon name="message" class="size-5" /></span>
                    <a href="{{ route('projects.in-app.edit', [$project, $message]) }}" class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="truncate font-semibold text-zinc-900 hover:underline dark:text-white">{{ $message->name }}</span>
                            <x-ui.badge :color="$color" :dot="$message->status === 'active'">{{ $label }}</x-ui.badge>
                        </span>
                        <span class="mt-0.5 block truncate text-sm text-zinc-500">
                            {{ $layouts[$message->layout] ?? $message->layout }} ·
                            {{ ($message->trigger['type'] ?? '') === 'event' ? __('On event :name', ['name' => $message->trigger['event'] ?? '']) : __('When the app opens') }}
                            @if ($message->segment) · {{ __($message->segment) }}@endif
                        </span>
                    </a>
                    @if ($message->hasAbTest())
                        @php
                            $rate = fn ($clicked, $shown) => $shown ? number_format($clicked / $shown * 100, 1, ',', ' ').' %' : '—';
                            $winner = $message->shown && $message->shown_b ? ($message->clicked / $message->shown >= $message->clicked_b / $message->shown_b ? 'A' : 'B') : null;
                        @endphp
                        <span class="flex items-center gap-3 rounded-lg bg-zinc-50 px-3 py-1.5 text-xs dark:bg-white/5">
                            <span class="font-semibold text-zinc-500">A/B</span>
                            <span @class(['tabular-nums', 'font-semibold text-emerald-700 dark:text-emerald-400' => $winner === 'A'])>A {{ $rate($message->clicked, $message->shown) }}</span>
                            <span @class(['tabular-nums', 'font-semibold text-emerald-700 dark:text-emerald-400' => $winner === 'B'])>B {{ $rate($message->clicked_b, $message->shown_b) }}</span>
                        </span>
                    @endif
                    <span class="grid grid-cols-3 gap-6 text-center">
                        @foreach ([[__('Shown'), $message->shown + $message->shown_b], [__('Clicked'), $message->clicked + $message->clicked_b], [__('Click rate'), ($message->shown + $message->shown_b) ? number_format(($message->clicked + $message->clicked_b) / ($message->shown + $message->shown_b) * 100, 1, ',', ' ').' %' : '—']] as [$what, $value])
                            <span>
                                <span class="block text-base font-semibold tabular-nums text-zinc-900 dark:text-white">{{ is_numeric($value) ? $n($value) : $value }}</span>
                                <span class="block text-xs text-zinc-500">{{ $what }}</span>
                            </span>
                        @endforeach
                    </span>
                    @can('send', $project)
                        <form method="POST" action="{{ route('projects.in-app.status', [$project, $message]) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="status" value="{{ $message->status === 'active' ? 'paused' : 'active' }}">
                            <x-ui.button size="sm" :variant="$message->status === 'active' ? 'secondary' : 'primary'" :icon="$message->status === 'active' ? 'pause' : 'play'">{{ $message->status === 'active' ? __('Pause') : __('Activate') }}</x-ui.button>
                        </form>
                    @endcan
                </li>
            @empty
                <li><x-ui.empty icon="message" :title="__('No in-app message yet')" :description="__('Announce a new feature, an offer or an update to the people who open the app.')">
                    @can('send', $project)<x-ui.button :href="route('projects.in-app.create', $project)" icon="plus">{{ __('New in-app message') }}</x-ui.button>@endcan
                </x-ui.empty></li>
            @endforelse
        </ul>
    </div>
</x-layouts.app>
