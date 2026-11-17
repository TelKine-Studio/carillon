@props(['subscription', 'details' => false])
{{-- State of a device; "details" adds the reason under the badge (status details). --}}
@php $reason = $subscription->statusDetails(); @endphp
<span class="inline-flex min-w-0 flex-col items-start gap-1">
    @if ($subscription->invalid_at)
        <x-ui.badge color="red" :title="$reason">{{ __('Unreachable') }}</x-ui.badge>
    @elseif (! $subscription->enabled)
        <x-ui.badge color="zinc" :title="$reason">{{ __('Unsubscribed') }}</x-ui.badge>
    @else
        <x-ui.badge color="green" dot>{{ __('Subscribed') }}</x-ui.badge>
    @endif
    @if ($details && $reason)
        <span class="max-w-full truncate text-xs text-zinc-500 dark:text-zinc-400" title="{{ $reason }}">{{ $reason }}</span>
    @endif
</span>
