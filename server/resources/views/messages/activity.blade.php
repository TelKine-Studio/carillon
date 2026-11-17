@php
    $platforms = ['ios' => ['iOS', 'apple'], 'android' => ['Android', 'android'], 'huawei' => ['Huawei', 'android'], 'amazon' => ['Amazon', 'android'], 'web' => ['Web', 'globe-web'], 'email' => [__('Email'), 'mail'], 'sms' => ['SMS', 'message'], 'whatsapp' => ['WhatsApp', 'chat']];
    $timeColumn = match ($tab) {
        'received' => ['received_at', __('Received at')],
        'clicked' => ['clicked_at', __('Clicked at')],
        default => ['sent_at', __('Sent at')],
    };
    $showError = in_array($tab, ['refused', 'failed'], true);
@endphp
@if ($deliveries->isEmpty())
    <div class="px-6 py-12 text-center">
        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ __('Nobody here') }}</p>
        <p class="mx-auto mt-1 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">
            @if ($message->successful > 0 && ($message->started_at ?? $message->created_at)->lt(now()->subDays(max(1, app(\App\Support\Settings::class)->integer('messages.retention_days', 90)))))
                {{ __('The details per device are kept :days days; the totals above stay.', ['days' => app(\App\Support\Settings::class)->integer('messages.retention_days', 90)]) }}
            @else
                {{ __('You will see the devices here as soon as there is activity.') }}
            @endif
        </p>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full min-w-[560px] text-sm">
            <thead>
                <tr class="border-b border-zinc-100 text-left text-xs text-zinc-500 dark:border-zinc-800">
                    <th class="px-5 py-2.5 font-medium sm:px-6">{{ __('Device') }}</th>
                    <th class="px-3 py-2.5 font-medium">{{ __('Person') }}</th>
                    <th class="px-3 py-2.5 font-medium">{{ $showError ? __('Reason') : __('Platform') }}</th>
                    <th class="px-5 py-2.5 text-right font-medium sm:px-6">{{ $timeColumn[1] }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($deliveries as $delivery)
                    @php
                        $device = $delivery->subscription;
                        [$platformName, $platformIcon] = $platforms[$device?->type] ?? [$device?->type ?? '—', 'smartphone'];
                        $at = $delivery->{$timeColumn[0]};
                    @endphp
                    <tr class="transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                        <td class="px-5 py-3 sm:px-6">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400"><x-ui.icon :name="$platformIcon" class="size-3.5" /></span>
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $device?->test_name ?? $device?->device_model ?? $platformName }}</span>
                                    <span class="block truncate font-mono text-[11px] text-zinc-400">{{ \Illuminate\Support\Str::limit((string) $delivery->subscription_id, 13, '…') }}@if ($device?->app_version) · v{{ $device->app_version }}@endif</span>
                                </span>
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            @if ($device?->subscriber_id)
                                <a href="{{ route('projects.subscribers.show', [$project, $device->subscriber_id]) }}" class="block max-w-48 truncate font-mono text-xs text-zinc-700 hover:text-brand-700 dark:text-zinc-300 dark:hover:text-brand-300">{{ $device->subscriber?->external_id ?? __('Anonymous') }}</a>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            @if ($showError)
                                <span class="block max-w-56 truncate font-mono text-xs text-red-600 dark:text-red-400" title="{{ $delivery->error }}">{{ $delivery->error ?? '—' }}</span>
                            @else
                                <span class="text-zinc-600 dark:text-zinc-400">{{ $platformName }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap text-zinc-600 tabular-nums sm:px-6 dark:text-zinc-400" title="{{ $at?->isoFormat('LLL:ss') }}">{{ $at?->isoFormat('D MMM, HH:mm') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($deliveries->hasPages())
        <div data-activity-pages class="flex items-center justify-between gap-3 border-t border-zinc-100 px-5 py-3 text-sm text-zinc-500 sm:px-6 dark:border-zinc-800">
            <span class="tabular-nums">{{ __(':from–:to of :total', ['from' => number_format($deliveries->firstItem(), 0, ',', ' '), 'to' => number_format($deliveries->lastItem(), 0, ',', ' '), 'total' => number_format($deliveries->total(), 0, ',', ' ')]) }}</span>
            <span class="flex gap-2">
                @if ($deliveries->previousPageUrl())
                    <a href="{{ $deliveries->previousPageUrl() }}" class="inline-flex h-8 items-center gap-1 rounded-md border border-zinc-300 bg-white px-2.5 text-sm font-medium text-zinc-800 transition-colors hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"><x-ui.icon name="chevron-left" class="size-3.5" />{{ __('Previous') }}</a>
                @endif
                @if ($deliveries->nextPageUrl())
                    <a href="{{ $deliveries->nextPageUrl() }}" class="inline-flex h-8 items-center gap-1 rounded-md border border-zinc-300 bg-white px-2.5 text-sm font-medium text-zinc-800 transition-colors hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Next') }}<x-ui.icon name="chevron-right" class="size-3.5" /></a>
                @endif
            </span>
        </div>
    @endif
@endif
