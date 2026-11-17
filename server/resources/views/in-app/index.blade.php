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

    <div class="card overflow-hidden">
        @if ($messages->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-medium text-zinc-500 dark:border-zinc-800 dark:bg-white/[.03] dark:text-zinc-400">
                            <th class="px-4 py-3 font-medium sm:px-5">{{ __('Name') }}</th>
                            <th class="px-3 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-3 py-3 text-right font-medium">{{ __('Shown') }}</th>
                            <th class="px-3 py-3 text-right font-medium">{{ __('Clicked') }}</th>
                            <th class="px-3 py-3 text-right font-medium whitespace-nowrap">{{ __('Click rate') }}</th>
                            <th class="px-4 py-3 sm:px-5"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($messages as $message)
                            @php
                                [$dot, $label] = match ($message->status) {
                                    'active' => ['bg-emerald-500', __('Active')],
                                    'paused' => ['bg-amber-500', __('Paused')],
                                    default => ['bg-zinc-400', __('Draft')],
                                };
                                $shown = $message->shown + $message->shown_b;
                                $clicked = $message->clicked + $message->clicked_b;
                                $rate = fn ($c, $v) => $v ? number_format($c / $v * 100, 1, ',', ' ').' %' : '—';
                            @endphp
                            <tr class="relative transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                                <td class="max-w-0 px-4 py-3.5 sm:px-5" style="width: 45%">
                                    <a href="{{ route('projects.in-app.edit', [$project, $message]) }}" class="block truncate font-medium text-zinc-900 after:absolute after:inset-0 dark:text-white">{{ $message->name }}</a>
                                    <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $layouts[$message->layout] ?? $message->layout }} ·
                                        {{ ($message->trigger['type'] ?? '') === 'event' ? __('On event :name', ['name' => $message->trigger['event'] ?? '']) : __('When the app opens') }}
                                        @if ($message->segment) · {{ __($message->segment) }}@endif
                                        @if ($message->hasAbTest()) · A/B : A {{ $rate($message->clicked, $message->shown) }}, B {{ $rate($message->clicked_b, $message->shown_b) }}@endif
                                    </p>
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap"><span class="inline-flex items-center gap-1.5 text-zinc-700 dark:text-zinc-300"><span class="size-2 rounded-full {{ $dot }}"></span>{{ $label }}</span></td>
                                <td class="px-3 py-3.5 text-right font-medium text-zinc-900 tabular-nums dark:text-white">{{ $n($shown) }}</td>
                                <td class="px-3 py-3.5 text-right font-medium text-zinc-900 tabular-nums dark:text-white">{{ $n($clicked) }}</td>
                                <td class="px-3 py-3.5 text-right font-medium whitespace-nowrap text-zinc-900 tabular-nums dark:text-white">{{ $rate($clicked, $shown) }}</td>
                                <td class="relative z-10 px-4 py-3.5 text-right sm:px-5">
                                    @can('send', $project)
                                        <form method="POST" action="{{ route('projects.in-app.status', [$project, $message]) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="status" value="{{ $message->status === 'active' ? 'paused' : 'active' }}">
                                            <x-ui.button size="sm" :variant="$message->status === 'active' ? 'secondary' : 'primary'" :icon="$message->status === 'active' ? 'pause' : 'play'">{{ $message->status === 'active' ? __('Pause') : __('Activate') }}</x-ui.button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-ui.empty icon="message" :title="__('No in-app message yet')" :description="__('Announce a new feature, an offer or an update to the people who open the app.')">
                @can('send', $project)<x-ui.button :href="route('projects.in-app.create', $project)" icon="plus">{{ __('New in-app message') }}</x-ui.button>@endcan
            </x-ui.empty>
        @endif
    </div>
</x-layouts.app>
