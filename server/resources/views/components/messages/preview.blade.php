@props(['project'])
{{--
    Notification as shown by each platform. Reads the "preview" object of
    the surrounding Alpine scope: {title, subtitle, body, image, buttons}.
--}}
<div x-data="{ platform: 'ios' }" {{ $attributes->merge(['class' => 'space-y-4']) }}>
    <div class="grid grid-cols-3 gap-1 rounded-lg bg-zinc-100 p-1 text-sm dark:bg-white/5">
        @foreach (['ios' => 'iOS', 'android' => 'Android', 'web' => 'Web'] as $key => $label)
            <button type="button" x-on:click="platform = '{{ $key }}'" class="rounded-md py-1.5 font-medium transition"
                :class="platform === '{{ $key }}' ? 'bg-white text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'">{{ $label }}</button>
        @endforeach
    </div>

    {{-- iOS: lock screen --}}
    <div x-show="platform === 'ios'" class="overflow-hidden rounded-lg bg-linear-to-br from-slate-700 via-slate-800 to-zinc-900 p-4 pt-10">
        <p class="text-center text-5xl font-light tracking-tight text-white/90 tabular-nums">9:41</p>
        <div class="mt-8 rounded-[20px] bg-white/75 p-3 shadow-lg backdrop-blur-xl dark:bg-zinc-800/80">
            <div class="flex gap-2.5">
                <x-ui.project-icon :project="$project" size="sm" class="!rounded-[8px]" />
                <div class="min-w-0 flex-1 text-[13px] leading-snug">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="truncate font-semibold text-zinc-900 dark:text-white" x-text="preview.title || @js($project->name)"></p>
                        <span class="shrink-0 text-[11px] text-zinc-500">{{ __('now') }}</span>
                    </div>
                    <p class="truncate font-medium text-zinc-800 dark:text-zinc-100" x-show="preview.subtitle" x-text="preview.subtitle"></p>
                    <p class="line-clamp-4 text-zinc-800 dark:text-zinc-200" x-text="preview.body || @js(__('Your message'))"></p>
                </div>
                <template x-if="preview.image">
                    <img :src="preview.image" alt="" class="size-10 shrink-0 rounded-md object-cover" x-on:error="$el.style.display = 'none'">
                </template>
            </div>
        </div>
        <template x-if="preview.buttons.length">
            <div class="mt-1.5 overflow-hidden rounded-[14px] bg-white/75 backdrop-blur-xl dark:bg-zinc-800/80">
                <template x-for="button in preview.buttons" :key="button">
                    <p class="border-t border-zinc-900/10 px-3 py-2 text-[13px] text-zinc-900 first:border-0 dark:border-white/10 dark:text-white" x-text="button"></p>
                </template>
            </div>
        </template>
    </div>

    {{-- Android: notification shade --}}
    <div x-show="platform === 'android'" x-cloak class="rounded-lg bg-zinc-200 p-3 dark:bg-zinc-800">
        <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-zinc-900">
            <div class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                <x-ui.icon name="bell" class="size-3 text-brand-600" />
                <span class="truncate">{{ $project->name }}</span><span>· {{ __('now') }}</span>
            </div>
            <div class="mt-1.5 flex gap-3">
                <div class="min-w-0 flex-1 text-[13px] leading-snug">
                    <p class="truncate font-semibold text-zinc-900 dark:text-white" x-text="preview.title || @js($project->name)"></p>
                    <p class="line-clamp-3 text-zinc-600 dark:text-zinc-300" x-text="preview.body || @js(__('Your message'))"></p>
                </div>
                {{-- Large icon: the message's, else the project's. --}}
                <template x-if="preview.icon">
                    <img :src="preview.icon" alt="" class="size-10 shrink-0 rounded-full object-cover" x-on:error="$el.style.display = 'none'">
                </template>
                @if ($project->icon_path)
                    <template x-if="!preview.icon"><x-ui.project-icon :project="$project" size="md" class="!rounded-full" /></template>
                @endif
            </div>
            <template x-if="preview.image">
                <img :src="preview.image" alt="" class="mt-2.5 max-h-40 w-full rounded object-cover" x-on:error="$el.style.display = 'none'">
            </template>
            <template x-if="preview.buttons.length">
                <div class="mt-2.5 flex gap-4">
                    <template x-for="button in preview.buttons" :key="button">
                        <span class="text-[12px] font-semibold text-brand-700 uppercase dark:text-brand-300" x-text="button"></span>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- Web: desktop notification --}}
    <div x-show="platform === 'web'" x-cloak class="rounded-lg bg-zinc-100 p-4 dark:bg-zinc-800/60">
        <div class="ml-auto max-w-xs rounded-lg border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex gap-3">
                <template x-if="preview.icon">
                    <img :src="preview.icon" alt="" class="size-10 shrink-0 rounded-md object-cover" x-on:error="$el.style.display = 'none'">
                </template>
                <template x-if="!preview.icon"><x-ui.project-icon :project="$project" size="md" class="!rounded-md" /></template>
                <div class="min-w-0 flex-1 text-[13px] leading-snug">
                    <p class="truncate font-semibold text-zinc-900 dark:text-white" x-text="preview.title || @js($project->name)"></p>
                    <p class="line-clamp-3 text-zinc-600 dark:text-zinc-300" x-text="preview.body || @js(__('Your message'))"></p>
                    <p class="mt-1 truncate text-[11px] text-zinc-400">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</p>
                </div>
            </div>
            <template x-if="preview.image">
                <img :src="preview.image" alt="" class="mt-2.5 max-h-40 w-full rounded object-cover" x-on:error="$el.style.display = 'none'">
            </template>
            <template x-if="preview.buttons.length">
                <div class="mt-2.5 grid grid-cols-2 gap-2">
                    <template x-for="button in preview.buttons.slice(0, 2)" :key="button">
                        <span class="truncate rounded-md bg-zinc-100 px-2 py-1.5 text-center text-[12px] font-medium text-zinc-700 dark:bg-white/10 dark:text-zinc-200" x-text="button"></span>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
