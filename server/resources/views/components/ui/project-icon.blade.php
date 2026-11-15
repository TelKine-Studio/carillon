@props(['project', 'size' => 'md'])
@php
    $palette = [
        'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
        'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
        'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
        'bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300',
    ];
    $color = $palette[crc32($project->app_id) % count($palette)];
    $sizes = ['xs' => 'size-6 rounded-md text-[11px]', 'sm' => 'size-8 rounded-lg text-sm', 'md' => 'size-10 rounded-xl text-base', 'lg' => 'size-14 rounded-2xl text-xl'];
@endphp
<span {{ $attributes->merge(['class' => "relative inline-flex shrink-0 items-center justify-center overflow-hidden font-semibold {$color} {$sizes[$size]}"]) }}>
    @if ($project->icon_path)
        <img src="{{ route('projects.icon', ['project' => $project, 'v' => substr(md5($project->icon_path), 0, 8)]) }}" alt="" class="size-full object-cover" loading="lazy">
    @else
        {{ $project->initials() ?: '#' }}
    @endif
</span>
