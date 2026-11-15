@php
    $locale = in_array(app()->getLocale(), array_keys($languages), true) ? app()->getLocale() : 'en';
    $texts = collect($source?->contents ?? [$locale => ''])->keys()->map(fn ($language) => [
        'language' => $language,
        'title' => $source?->headings[$language] ?? '',
        'subtitle' => $source?->subtitle[$language] ?? '',
        'body' => $source?->contents[$language] ?? '',
    ])->values()->all();
    $targeting = $source?->targeting ?? [];
    $ready = array_keys(array_filter($platforms, fn ($p) => $p['ready']));
    $config = [
        'estimateUrl' => route('projects.messages.estimate', $project),
        'languages' => $languages,
        'texts' => old('texts', $texts),
        'audience' => old('audience', ! empty($targeting['external_ids']) ? 'users' : (! empty($targeting['subscription_ids']) ? 'devices' : 'segments')),
        'included' => old('included_segments', $targeting['segments'] ?? ['Subscribed Users']),
        'excluded' => old('excluded_segments', $targeting['excluded_segments'] ?? []),
        'platforms' => old('platforms', $targeting['platforms'] ?? ($ready ?: ['ios', 'android', 'web'])),
        'image' => old('big_picture', $source?->option('big_picture')),
        'buttons' => old('buttons', collect($source?->option('buttons', []))->map(fn ($b) => ['id' => $b['id'] ?? '', 'text' => $b['text'] ?? ''])->all()),
        'data' => old('data', collect($source?->option('data', []))->map(fn ($v, $k) => ['key' => $k, 'value' => is_scalar($v) ? (string) $v : json_encode($v)])->values()->all()),
        'schedule' => old('schedule', 'now'),
    ];
    $externalIds = old('external_ids', implode(PHP_EOL, $targeting['external_ids'] ?? []));
    $deviceIds = old('subscription_ids', implode(PHP_EOL, $targeting['subscription_ids'] ?? []));
    $builtIn = ['Subscribed Users' => __('Subscribed Users'), 'Active Users' => __('Active Users'), 'Engaged Users' => __('Engaged Users'), 'Inactive Users' => __('Inactive Users')];
@endphp
<x-layouts.app :title="__('New message').' · '.$project->name">
    <x-ui.page-header :title="__('New message')" :description="__('Write a push notification and choose who receives it.')">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.messages.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Messages') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span>{{ __('New') }}</span>
        </x-slot:breadcrumb>
    </x-ui.page-header>

    @if ($errors->has('alert'))
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />{{ $errors->first('alert') }}
        </div>
    @endif

    <form method="POST" action="{{ route('projects.messages.store', $project) }}" x-data="messageComposer(@js($config))" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        @csrf
        <input type="hidden" name="timezone" x-init="$el.value = Intl.DateTimeFormat().resolvedOptions().timeZone">

        <div class="min-w-0 space-y-6">
            {{-- Audience --}}
            <x-ui.card :title="__('Audience')" icon="users">
                <div class="grid grid-cols-3 gap-1 rounded-lg bg-zinc-100 p-1 text-sm dark:bg-white/5">
                    @foreach (['segments' => __('Segments'), 'users' => __('Users'), 'devices' => __('Devices')] as $key => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="audience" value="{{ $key }}" x-model="audience" class="sr-only">
                            <span class="block rounded-md py-1.5 text-center font-medium transition" :class="audience === '{{ $key }}' ? 'bg-white text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div x-show="audience === 'segments'" class="mt-5 space-y-5">
                    <div>
                        <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Send to') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($builtIn + $segments->mapWithKeys(fn ($s) => [$s => $s])->all() as $value => $label)
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="included_segments[]" value="{{ $value }}" x-model="included" class="peer sr-only">
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-zinc-200 px-2.5 py-1.5 text-sm text-zinc-700 transition peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-800 hover:border-zinc-300 dark:border-zinc-700 dark:text-zinc-300 dark:peer-checked:border-brand-500/60 dark:peer-checked:bg-brand-500/10 dark:peer-checked:text-brand-200">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <details class="group" @if (! empty($config['excluded'])) open @endif>
                        <summary class="flex cursor-pointer items-center gap-2 text-sm font-medium text-zinc-600 select-none dark:text-zinc-400">
                            <x-ui.icon name="chevron-right" class="size-4 text-zinc-400 transition group-open:rotate-90" />{{ __('Exclude segments') }}
                        </summary>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($builtIn + $segments->mapWithKeys(fn ($s) => [$s => $s])->all() as $value => $label)
                                @continue($value === 'Subscribed Users')
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="excluded_segments[]" value="{{ $value }}" x-model="excluded" class="peer sr-only">
                                    <span class="inline-flex items-center rounded-md border border-zinc-200 px-2.5 py-1.5 text-sm text-zinc-700 transition peer-checked:border-red-400 peer-checked:bg-red-50 peer-checked:text-red-800 dark:border-zinc-700 dark:text-zinc-300 dark:peer-checked:bg-red-500/10 dark:peer-checked:text-red-200">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                </div>

                <div x-show="audience === 'users'" x-cloak class="mt-5">
                    <x-ui.textarea name="external_ids" rows="3" :label="__('External ids')" :value="$externalIds" placeholder="user-42, user-43"
                        :hint="__('The ids your app gives to Carillon when the user logs in. Separated by commas or new lines, up to 20,000.')" x-on:input.debounce.500ms="refresh()" />
                </div>

                <div x-show="audience === 'devices'" x-cloak class="mt-5">
                    <x-ui.textarea name="subscription_ids" rows="3" :label="__('Device ids')" :value="$deviceIds"
                        :hint="__('Useful to test on your own devices: their id is shown in Audience.')" x-on:input.debounce.500ms="refresh()" />
                </div>

                <div class="mt-5 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                    <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Platforms') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($platforms as $key => $platform)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="platforms[]" value="{{ $key }}" x-model="platforms" class="peer sr-only">
                                <span class="inline-flex items-center gap-2 rounded-md border border-zinc-200 px-3 py-1.5 text-sm text-zinc-700 transition peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-800 dark:border-zinc-700 dark:text-zinc-300 dark:peer-checked:border-brand-500/60 dark:peer-checked:bg-brand-500/10 dark:peer-checked:text-brand-200">
                                    <x-ui.icon :name="$platform['icon']" class="size-4" />{{ $platform['label'] }}
                                    @unless ($platform['ready'])<span class="text-xs text-amber-600 dark:text-amber-400" title="{{ __('No push keys for this platform') }}">· {{ __('no keys') }}</span>@endunless
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </x-ui.card>

            {{-- Message --}}
            <x-ui.card :title="__('Message')" icon="message">
                <x-slot:actions>
                    <div x-data="{ open: false }" class="relative" x-show="unusedLanguages.length">
                        <x-ui.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="open = !open">{{ __('Language') }}</x-ui.button>
                        <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute right-0 z-20 mt-1 max-h-64 w-48 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-(--shadow-lift) dark:border-zinc-700 dark:bg-zinc-900">
                            <template x-for="[code, name] in unusedLanguages" :key="code">
                                <button type="button" x-on:click="addLanguage(code); open = false" class="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-left text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-white/5">
                                    <span x-text="name"></span><span class="font-mono text-xs text-zinc-400" x-text="code"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </x-slot:actions>

                <div class="mb-5 flex flex-wrap gap-1" x-show="texts.length > 1">
                    <template x-for="(text, index) in texts" :key="index">
                        <span class="inline-flex items-center rounded-md text-sm transition" :class="active === index ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300'">
                            <button type="button" class="py-1 pr-1 pl-2.5 font-medium uppercase" x-on:click="active = index" x-text="text.language"></button>
                            <button type="button" class="px-1.5 py-1 opacity-60 hover:opacity-100" x-on:click="removeLanguage(index)" x-show="texts.length > 1" title="{{ __('Remove') }}"><x-ui.icon name="x" class="size-3" /></button>
                        </span>
                    </template>
                </div>

                <template x-for="(text, index) in texts" :key="index">
                    <div x-show="active === index" class="space-y-5">
                        <input type="hidden" :name="`texts[${index}][language]`" :value="text.language">
                        <div class="space-y-1.5">
                            <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" :for="`title-${index}`">{{ __('Title') }} <span class="font-normal text-zinc-400">· <span class="uppercase" x-text="text.language"></span></span></label>
                            <input :id="`title-${index}`" :name="`texts[${index}][title]`" x-model="text.title" maxlength="500" class="form-control" placeholder="{{ __('e.g. Your order has shipped') }}">
                        </div>
                        <div class="space-y-1.5">
                            <label class="flex justify-between text-sm font-medium text-zinc-800 dark:text-zinc-200" :for="`subtitle-${index}`">{{ __('Subtitle') }} <span class="font-normal text-zinc-400">{{ __('iOS only') }}</span></label>
                            <input :id="`subtitle-${index}`" :name="`texts[${index}][subtitle]`" x-model="text.subtitle" maxlength="500" class="form-control">
                        </div>
                        <div class="space-y-1.5">
                            <label class="flex justify-between text-sm font-medium text-zinc-800 dark:text-zinc-200" :for="`body-${index}`">{{ __('Message') }} <span class="font-normal tabular-nums text-zinc-400" x-text="`${text.body.length} / 4000`"></span></label>
                            <textarea :id="`body-${index}`" :name="`texts[${index}][body]`" x-model="text.body" rows="4" maxlength="4000" required class="form-control resize-y" placeholder="{{ __('What do you want to say?') }}"></textarea>
                        </div>
                    </div>
                </template>
                <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Each device receives the text in its language; the others get the first one.') }}</p>
            </x-ui.card>

            {{-- Media and actions --}}
            <x-ui.card :title="__('Image and actions')" icon="image">
                <div class="space-y-5">
                    <x-ui.input name="big_picture" type="url" :label="__('Image')" placeholder="https://…/image.jpg" x-model="image" :hint="__('Large image of the notification (JPEG or PNG, ideally 2:1).')" optional />
                    <x-ui.input name="url" type="url" :label="__('Link')" :value="old('url', $source?->option('url'))" placeholder="https://… {{ __('or') }} myapp://…" :hint="__('Opened when the notification is tapped (web page or deep link of the app).')" optional />

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Buttons') }}</p>
                            <button type="button" class="text-sm font-medium text-brand-700 hover:underline disabled:opacity-40 dark:text-brand-300" x-on:click="buttons.push({ id: '', text: '' })" :disabled="buttons.length >= 3">+ {{ __('Add a button') }}</button>
                        </div>
                        <template x-for="(button, index) in buttons" :key="index">
                            <div class="mb-2 flex gap-2">
                                <input :name="`buttons[${index}][text]`" x-model="button.text" maxlength="100" class="form-control" placeholder="{{ __('Label') }}">
                                <input :name="`buttons[${index}][id]`" x-model="button.id" maxlength="64" class="form-control w-36 font-mono text-sm" placeholder="id">
                                <x-ui.button type="button" variant="ghost" size="icon" icon="x" class="size-[42px] shrink-0" x-on:click="buttons.splice(index, 1)"><span class="sr-only">{{ __('Remove') }}</span></x-ui.button>
                            </div>
                        </template>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400" x-show="!buttons.length">{{ __('Up to 3 buttons; your app receives the id of the one tapped.') }}</p>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Additional data') }}</p>
                            <button type="button" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300" x-on:click="data.push({ key: '', value: '' })">+ {{ __('Add a field') }}</button>
                        </div>
                        <template x-for="(row, index) in data" :key="index">
                            <div class="mb-2 flex gap-2">
                                <input :name="`data[${index}][key]`" x-model="row.key" class="form-control w-40 font-mono text-sm" placeholder="{{ __('key') }}">
                                <input :name="`data[${index}][value]`" x-model="row.value" class="form-control" placeholder="{{ __('value') }}">
                                <x-ui.button type="button" variant="ghost" size="icon" icon="x" class="size-[42px] shrink-0" x-on:click="data.splice(index, 1)"><span class="sr-only">{{ __('Remove') }}</span></x-ui.button>
                            </div>
                        </template>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400" x-show="!data.length">{{ __('Sent to your app with the notification (e.g. the id of an order).') }}</p>
                    </div>
                </div>
            </x-ui.card>

            {{-- Delivery --}}
            <x-ui.card :title="__('Delivery')" icon="clock">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (['now' => [__('Send now'), __('Delivered within seconds.')], 'later' => [__('Schedule'), __('At a date and time of your choice.')]] as $key => [$label, $text])
                        <label class="flex cursor-pointer gap-3 rounded-lg border border-zinc-200 p-3.5 transition has-checked:border-brand-500 has-checked:bg-brand-50/50 dark:border-zinc-800 dark:has-checked:bg-brand-500/5">
                            <input type="radio" name="schedule" value="{{ $key }}" x-model="schedule" class="mt-0.5">
                            <span><span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ $label }}</span><span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $text }}</span></span>
                        </label>
                    @endforeach
                </div>
                <div x-show="schedule === 'later'" x-cloak class="mt-5">
                    <x-ui.input name="send_at" type="datetime-local" min="today" :label="__('Date and time')" :hint="__('In your time zone.')" />
                </div>

                <button type="button" class="mt-5 flex items-center gap-2 text-sm font-medium text-zinc-600 dark:text-zinc-400" x-on:click="advanced = !advanced">
                    <x-ui.icon name="chevron-right" class="size-4 text-zinc-400 transition" ::class="advanced && 'rotate-90'" />{{ __('Advanced options') }}
                </button>
                <div x-show="advanced" x-collapse x-cloak>
                    <div class="grid gap-5 pt-5 sm:grid-cols-2">
                        <x-ui.select name="priority" :label="__('Priority')" :value="old('priority', $source?->option('priority', 10))" :options="[10 => __('High (immediate)'), 5 => __('Normal (battery friendly)')]" />
                        <x-ui.input name="ttl" type="number" min="0" max="2419200" :label="__('Time to live (seconds)')" :value="old('ttl', $source?->option('ttl'))" placeholder="{{ $project->setting('default_ttl') ?? 259200 }}" optional />
                        <x-ui.input name="collapse_id" :label="__('Collapse id')" :value="old('collapse_id', $source?->option('collapse_id'))" :hint="__('A new message with the same id replaces the previous one.')" optional />
                        <x-ui.input name="ios_badge" type="number" min="0" :label="__('iOS badge')" :value="old('ios_badge', $source?->option('ios_badgeCount'))" :hint="__('Number shown on the app icon.')" optional />
                        <x-ui.input name="ios_sound" :label="__('iOS sound')" :value="old('ios_sound', $source?->option('ios_sound'))" placeholder="default" optional />
                        <x-ui.input name="android_channel_id" :label="__('Android channel')" :value="old('android_channel_id', $source?->option('android_channel_id'))" optional />
                        <x-ui.input name="thread_id" :label="__('Thread (iOS grouping)')" :value="old('thread_id', $source?->option('thread_id'))" optional />
                        <x-ui.input name="name" :label="__('Internal name')" :value="old('name', $source ? $source->name : null)" :hint="__('Only shown in the dashboard.')" optional />
                    </div>
                </div>
            </x-ui.card>
        </div>

        {{-- Preview and send --}}
        <aside class="space-y-4 lg:sticky lg:top-6">
            <x-ui.card :title="__('Preview')" icon="smartphone">
                <x-messages.preview :project="$project" />
            </x-ui.card>

            <div class="card p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Recipients') }}</p>
                        <p class="mt-0.5 text-2xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-white">
                            <span x-show="estimate.total !== null" x-text="format(estimate.total)"></span><span x-show="estimate.total === null" class="text-zinc-300">—</span>
                        </p>
                    </div>
                    <x-ui.icon name="refresh" class="size-4 animate-spin text-zinc-400" x-show="estimate.loading" x-cloak />
                </div>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500" x-show="estimate.total">
                    @foreach (['ios' => 'iOS', 'android' => 'Android', 'web' => 'Web'] as $key => $label)
                        <span x-show="estimate.platforms.{{ $key }}">{{ $label }} <strong class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-300" x-text="format(estimate.platforms.{{ $key }})"></strong></span>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-amber-700 dark:text-amber-400" x-show="estimate.error" x-text="estimate.error" x-cloak></p>
                <p class="mt-3 text-xs text-amber-700 dark:text-amber-400" x-show="estimate.total === 0 && !estimate.error" x-cloak>{{ __('No subscribed device matches this audience yet.') }}</p>

                <x-ui.button class="mt-5 w-full" size="lg" icon="send">
                    <span x-text="schedule === 'later' ? @js(__('Schedule the message')) : @js(__('Send the message'))">{{ __('Send the message') }}</span>
                </x-ui.button>
            </div>
        </aside>
    </form>
</x-layouts.app>
