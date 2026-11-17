@props(['section'])
{{-- Instance settings: underlined tabs above the sections. --}}
@php
    $tabs = collect([
        'general' => __('General'),
        'security' => __('Security'),
        'mail' => __('Email'),
        'system' => __('System'),
    ])->map(fn ($label, $key) => [$label, route('admin.settings.edit', $key)])->all();
@endphp
<x-settings.tabs :tabs="$tabs" :current="$section" />
<div class="max-w-4xl space-y-6">
    {{ $slot }}
</div>
