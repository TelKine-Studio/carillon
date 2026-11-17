@props(['status', 'variant' => 'badge'])
@php
    [$color, $label] = match ($status) {
        'draft' => ['amber', __('Draft')],
        'scheduled' => ['violet', __('Scheduled')],
        'queued' => ['zinc', __('Queued')],
        'sending' => ['blue', __('Sending')],
        'sent' => ['green', __('Sent')],
        'canceled' => ['zinc', __('Canceled')],
        'failed' => ['red', __('Failed')],
        default => ['zinc', $status],
    };
@endphp
@if ($variant === 'dot')
    @php
        $dots = ['green' => 'bg-emerald-500', 'violet' => 'bg-violet-500', 'amber' => 'bg-amber-500', 'blue' => 'bg-sky-500', 'red' => 'bg-red-500', 'zinc' => 'bg-zinc-400'];
    @endphp
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-sm text-zinc-700 dark:text-zinc-300']) }}><span @class(['size-2 rounded-full', $dots[$color], 'animate-pulse' => in_array($status, ['sending', 'queued'], true)])></span>{{ $label }}</span>
@else
<x-ui.badge :color="$color" :dot="in_array($status, ['sending', 'queued'], true)" {{ $attributes }}>{{ $label }}</x-ui.badge>
@endif
