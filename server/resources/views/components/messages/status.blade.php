@props(['status'])
@php
    [$color, $label] = match ($status) {
        'scheduled' => ['violet', __('Scheduled')],
        'queued' => ['zinc', __('Queued')],
        'sending' => ['blue', __('Sending')],
        'sent' => ['green', __('Sent')],
        'canceled' => ['zinc', __('Canceled')],
        'failed' => ['red', __('Failed')],
        default => ['zinc', $status],
    };
@endphp
<x-ui.badge :color="$color" :dot="in_array($status, ['sending', 'queued'], true)" {{ $attributes }}>{{ $label }}</x-ui.badge>
