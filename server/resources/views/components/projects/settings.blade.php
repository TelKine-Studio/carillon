@props(['project', 'section'])
{{-- Project settings: underlined tabs above the sections. --}}
@php
    $tabs = collect([
        'general' => __('General'),
        'platforms' => __('Platforms'),
        'api-keys' => __('Keys & IDs'),
        'webhooks' => __('Webhooks'),
        'members' => __('Members'),
    ])->map(fn ($label, $key) => [$label, route('projects.settings', [$project, $key])])->all();
@endphp
<x-layouts.app :title="__('Settings').' · '.$project->name">
    <x-ui.page-header :title="__('Settings')" :description="__('Platforms, keys and members of :name.', ['name' => $project->name])" />
    <x-settings.tabs :tabs="$tabs" :current="$section" />
    <div class="max-w-4xl space-y-6">
        {{ $slot }}
    </div>
</x-layouts.app>
