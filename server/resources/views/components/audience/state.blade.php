@props(['subscription'])
@if ($subscription->invalid_at)
    <x-ui.badge color="red" :title="__('Refused by the provider: the app was probably uninstalled.')">{{ __('Unreachable') }}</x-ui.badge>
@elseif (! $subscription->enabled)
    <x-ui.badge color="zinc">{{ __('Unsubscribed') }}</x-ui.badge>
@else
    <x-ui.badge color="green" dot>{{ __('Subscribed') }}</x-ui.badge>
@endif
