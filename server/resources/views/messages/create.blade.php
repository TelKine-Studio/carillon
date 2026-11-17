@php
    // Personalization variables, written without double braces (Blade).
    $open = '{'.'{';
    $close = '}'.'}';
    $variable = fn ($name) => '<code class="font-mono">'.e($open.' '.$name.' '.$close).'</code>';
    // The same composer edits templates ($template set, or $templateMode for a new one).
    $template ??= null;
    $templateMode = ($templateMode ?? false) || $template !== null;
    $templates ??= collect();
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
        'estimateUrl' => $templateMode ? null : route('projects.messages.estimate', $project),
        'languages' => $languages,
        'texts' => old('texts', $texts),
        'audience' => old('audience', ! empty($targeting['external_ids']) ? 'users' : (! empty($targeting['subscription_ids']) ? 'devices' : 'segments')),
        'included' => old('included_segments', $targeting['segments'] ?? ['Subscribed Users']),
        'excluded' => old('excluded_segments', $targeting['excluded_segments'] ?? []),
        'platforms' => old('platforms', $targeting['platforms'] ?? ($ready ?: ['ios', 'android', 'web'])),
        'image' => old('big_picture', $source?->option('big_picture')),
        'icon' => old('icon', $source?->option('large_icon') ?? $source?->option('chrome_web_icon')),
        'buttons' => old('buttons', collect($source?->option('buttons', []))->map(fn ($b) => ['id' => $b['id'] ?? '', 'text' => $b['text'] ?? ''])->all()),
        'data' => old('data', collect($source?->option('data', []))->map(fn ($v, $k) => ['key' => $k, 'value' => is_scalar($v) ? (string) $v : json_encode($v)])->values()->all()),
        'schedule' => old('schedule', 'now'),
        'ab' => (bool) old('ab_enabled'),
        'channel' => old('channel', $source instanceof \App\Models\Message ? $source->channel : (in_array($source?->option('target_channel'), ['email', 'sms'], true) ? $source->option('target_channel') : 'push')),
        'whatsapp' => [
            'mode' => old('whatsapp_mode', $source?->option('whatsapp_template') ? 'template' : (($source instanceof \App\Models\Message && $source->channel === 'whatsapp') ? 'text' : 'template')),
            'template' => old('whatsapp_template', $source?->option('whatsapp_template') ? $source->option('whatsapp_template').'|'.$source->option('whatsapp_language') : ''),
            'variables' => old('whatsapp_variables', (array) $source?->option('whatsapp_variables', [])),
            'body' => old('whatsapp_body', ! $source?->option('whatsapp_template') && ($source instanceof \App\Models\Message ? $source->channel === 'whatsapp' : $source?->option('target_channel') === 'whatsapp') ? (string) \App\Models\Message::localized($source->contents, 'en') : ''),
        ],
        'smsBody' => old('sms_body', ($source instanceof \App\Models\Message ? $source->channel === 'sms' : $source?->option('target_channel') === 'sms') ? (string) \App\Models\Message::localized($source->contents, 'en') : ''),
        'emailSubject' => old('email_subject', is_string($source?->option('email_subject')) ? $source->option('email_subject') : ''),
        'emailPreheader' => old('email_preheader', is_string($source?->option('email_preheader')) ? $source->option('email_preheader') : ''),
        'starterEmail' => view('email.starter')->render(),
        'emailBody' => old('email_body', is_string($source?->option('email_body')) ? $source->option('email_body') : ''),
    ];
    $emailDesign = old('email_design') ? json_decode(old('email_design'), true) : $source?->option('email_design');
    $emailDesign = is_array($emailDesign) && is_array($emailDesign['blocks'] ?? null) ? $emailDesign : null;
    $designerLabels = ['heading' => __('Hello').' '.$open.' tags.first_name | default: "'.__('there').'" '.$close, 'text' => __('Write your message here.'), 'button' => __('Discover'), 'unsubscribe' => __('Unsubscribe')];
    $blockTypes = ['heading' => __('Title'), 'text' => __('Text'), 'image' => __('Image'), 'button' => __('Button'), 'divider' => __('Divider'), 'spacer' => __('Space')];
    $externalIds = old('external_ids', implode(PHP_EOL, $targeting['external_ids'] ?? []));
    $deviceIds = old('subscription_ids', implode(PHP_EOL, $targeting['subscription_ids'] ?? []));
    $builtIn = ['Subscribed Users' => __('Subscribed Users'), 'Active Users' => __('Active Users'), 'Engaged Users' => __('Engaged Users'), 'Inactive Users' => __('Inactive Users'), 'iOS Users' => __('iOS Users'), 'Android Users' => __('Android Users'), 'Web Users' => __('Web Users'), 'Test Devices' => __('Test Devices')];
@endphp
@php
    $heading = $templateMode ? ($template ? __('Edit the template') : __('New template')) : __('New message');
@endphp
<x-layouts.app :title="$heading.' · '.$project->name">
    <x-ui.page-header :title="$heading" :description="$templateMode ? __('Content you can reuse from the composer or with template_id in the API.') : __('Write a push notification or an email, and choose who receives it.')">
        <x-slot:breadcrumb>
            @if ($templateMode)
                <a href="{{ route('projects.templates.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Templates') }}</a>
            @else
                <a href="{{ route('projects.messages.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Messages') }}</a>
            @endif
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span>{{ $template ? $template->name : __('New') }}</span>
        </x-slot:breadcrumb>
        @if (! $templateMode && $templates->isNotEmpty())
            <div x-data="{ open: false }" class="relative">
                <x-ui.button type="button" variant="secondary" icon="file" x-on:click="open = !open">{{ __('Start from a template') }}</x-ui.button>
                <div x-show="open" x-cloak x-on:click.outside="open = false" x-transition.opacity class="absolute right-0 z-30 mt-1 max-h-72 w-64 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-(--shadow-lift) dark:border-zinc-800 dark:bg-zinc-900">
                    @foreach ($templates as $item)
                        <a href="{{ route('projects.messages.create', [$project, 'template' => $item->id]) }}" class="block rounded-md px-2.5 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-white/5">
                            <span class="block truncate font-medium text-zinc-900 dark:text-white">{{ $item->name }}</span>
                            <span class="block truncate text-xs text-zinc-500">{{ \App\Models\Message::localized($item->headings, app()->getLocale()) ?? \App\Models\Message::localized($item->contents, app()->getLocale()) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </x-ui.page-header>

    @if ($errors->has('alert'))
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />{{ $errors->first('alert') }}
        </div>
    @endif

    <form method="POST" action="{{ $template ? route('projects.templates.update', [$project, $template]) : ($templateMode ? route('projects.templates.store', $project) : route('projects.messages.store', $project)) }}" x-data="messageComposer(@js($config))" x-on:submit="$event.submitter?.classList.contains('send-plane') && $event.submitter.classList.add('is-sending')" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        @csrf
        @if ($template)
            @method('PUT')
        @endif
        @if ($usedTemplate ?? null)
            <input type="hidden" name="template_id" value="{{ $usedTemplate->id }}">
        @endif
        <input type="hidden" name="timezone" x-init="$el.value = Intl.DateTimeFormat().resolvedOptions().timeZone">

        <div class="min-w-0 space-y-6">
            @if ($templateMode)
                <x-ui.card :title="__('Template')" icon="file">
                    <x-ui.input name="template_name" :label="__('Name')" :value="old('template_name', $template?->name)" required maxlength="255" :placeholder="__('e.g. Order shipped')" :hint="__('Only shown in the dashboard and the API.')" />
                </x-ui.card>
            @endif

            @if ($usedTemplate ?? null)
                <div class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-white/5 dark:text-zinc-400">
                    <x-ui.icon name="file" class="size-4 shrink-0 text-zinc-400" />
                    <span class="min-w-0 flex-1">{!! __('Started from the template :name: change anything before sending.', ['name' => '<strong class="font-semibold text-zinc-900 dark:text-white">'.e($usedTemplate->name).'</strong>']) !!}</span>
                </div>
            @endif

            {{-- Channel --}}
            @if ($template)
                <input type="hidden" name="channel" :value="channel">
            @else
                <input type="hidden" name="channel" :value="channel">
                <div class="grid grid-cols-2 gap-1 rounded-lg border border-zinc-200 bg-white p-1 text-sm shadow-(--shadow-soft) sm:grid-cols-4 dark:border-zinc-800 dark:bg-zinc-900">
                    @foreach (['push' => [__('Push'), 'smartphone'], 'email' => [__('Email'), 'mail'], 'sms' => ['SMS', 'message'], 'whatsapp' => ['WhatsApp', 'chat']] as $key => [$label, $icon])
                        <button type="button" x-on:click="channel = '{{ $key }}'" class="flex items-center justify-center gap-2 rounded-md py-2 font-medium transition"
                            :class="channel === '{{ $key }}' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'">
                            <x-ui.icon :name="$icon" class="size-4" />{{ $label }}
                        </button>
                    @endforeach
                </div>
                @unless ($project->credential('email')?->isUsable())
                    <div x-show="channel === 'email'" x-cloak class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                        <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                        <span>{!! __('Emails are not set up for this project yet: choose how they are sent in :settings.', ['settings' => '<a class="font-medium underline" href="'.route('projects.settings', [$project, 'platforms']).'">'.e(__('Platforms')).'</a>']) !!}</span>
                    </div>
                @endunless
                @unless ($project->credential('whatsapp')?->isUsable())
                    <div x-show="channel === 'whatsapp'" x-cloak class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                        <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                        <span>{!! __('WhatsApp is not set up for this project yet: connect your WhatsApp Business number in :settings.', ['settings' => '<a class="font-medium underline" href="'.route('projects.settings', [$project, 'platforms']).'">'.e(__('Platforms')).'</a>']) !!}</span>
                    </div>
                @endunless
                @unless ($project->credential('sms')?->isUsable())
                    <div x-show="channel === 'sms'" x-cloak class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                        <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                        <span>{!! __('SMS are not set up for this project yet: connect Twilio or Orange in :settings.', ['settings' => '<a class="font-medium underline" href="'.route('projects.settings', [$project, 'platforms']).'">'.e(__('Platforms')).'</a>']) !!}</span>
                    </div>
                @endunless
            @endif

            {{-- Audience --}}
            @unless ($templateMode)
            <x-ui.card class="composer-step" :title="__('Audience')" icon="users">
                <div class="grid grid-cols-3 gap-1 rounded-lg bg-zinc-100 p-1 text-sm dark:bg-white/5">
                    @foreach (['segments' => __('Segments'), 'users' => __('Users'), 'devices' => __('Devices')] as $key => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="audience" value="{{ $key }}" x-model="audience" class="sr-only">
                            <span class="block rounded-md py-1.5 text-center font-medium transition" :class="audience === '{{ $key }}' ? 'bg-white text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                                @if ($key === 'devices') x-text="channel === 'email' ? @js(__('Addresses')) : (['sms', 'whatsapp'].includes(channel) ? @js(__('Numbers')) : @js($label))" @endif>{{ $label }}</span>
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

                <div x-show="audience === 'devices' && channel === 'push'" x-cloak class="mt-5">
                    <x-ui.textarea name="subscription_ids" rows="3" :label="__('Device ids')" :value="$deviceIds"
                        :hint="__('Useful to test on your own devices: their id is shown in Audience.')" x-on:input.debounce.500ms="refresh()" />
                </div>
                <div x-show="audience === 'devices' && ['sms', 'whatsapp'].includes(channel)" x-cloak class="mt-5">
                    <x-ui.textarea name="phone_numbers" rows="3" :label="__('Phone numbers')" :value="old('phone_numbers')" placeholder="+2250700000000"
                        :hint="__('Numbers already in your audience, in the international format, one per line.')" x-on:input.debounce.500ms="refresh()" />
                </div>
                <div x-show="audience === 'devices' && channel === 'email'" x-cloak class="mt-5">
                    <x-ui.textarea name="email_addresses" rows="3" :label="__('Email addresses')" :value="old('email_addresses')" placeholder="ada@example.com"
                        :hint="__('Addresses already in your audience, separated by commas or new lines.')" x-on:input.debounce.500ms="refresh()" />
                </div>

                <div x-show="channel === 'push'" class="mt-5 border-t border-zinc-100 pt-5 dark:border-zinc-800">
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
            @endunless

            {{-- Email --}}
            <x-ui.card class="composer-step" :title="__('Email')" icon="mail" x-show="channel === 'email'" x-cloak>
                <div class="space-y-5">
                    <x-ui.input name="email_subject" :label="__('Subject')" x-model="emailSubject" maxlength="255" :placeholder="__('e.g. Our new collection is here')" />
                    <x-ui.input name="email_preheader" :label="__('Preview text')" x-model="emailPreheader" maxlength="255" :hint="__('Shown after the subject in the inbox.')" optional />
                    <x-ui.input name="email_from_name" :label="__('Sender name')" :value="old('email_from_name', $source?->option('email_from_name'))" :placeholder="$project->credential('email')?->detail('from_name') ?: $project->name" optional />
                    <div class="space-y-3" x-data="emailDesigner(@js($emailDesign), @js($designerLabels))">
                        <div class="flex items-center justify-between gap-3">
                            <label for="email_body" class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Content') }}</label>
                            <div class="inline-flex gap-0.5 rounded-lg bg-zinc-100 p-0.5 text-xs dark:bg-white/5">
                                @foreach (['visual' => [__('Visual'), 'layers'], 'html' => ['HTML', 'code']] as $key => [$label, $icon])
                                    <button type="button" x-on:click="{{ $key === 'visual' ? 'useVisual()' : 'useHtml()' }}" class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 font-medium transition"
                                        :class="mode === '{{ $key }}' ? 'bg-white text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'">
                                        <x-ui.icon :name="$icon" class="size-3.5" />{{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <input type="hidden" name="email_body" :value="emailBody">
                        <input type="hidden" name="email_design" :value="designJson">

                        <div x-show="askReplace" x-cloak class="flex flex-wrap items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                            <span class="min-w-0 flex-1">{{ __('The visual editor starts from a new layout: the HTML written here will be replaced.') }}</span>
                            <x-ui.button type="button" size="sm" variant="secondary" x-on:click="askReplace = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="button" size="sm" x-on:click="useVisual(true)">{{ __('Replace') }}</x-ui.button>
                        </div>

                        {{-- Visual --}}
                        <div x-show="mode === 'visual'" class="space-y-3">
                            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-zinc-500">
                                <span class="flex items-center gap-2">{{ __('Color') }}
                                    <template x-for="color in accents" :key="color">
                                        <button type="button" x-on:click="design.accent = color" class="size-5 rounded-full ring-offset-2 transition ring-offset-white dark:ring-offset-zinc-900" :class="design?.accent === color ? 'ring-2 ring-zinc-900 dark:ring-white' : 'hover:scale-110'" :style="`background:${color}`" :title="color"></button>
                                    </template>
                                </span>
                                <span class="flex items-center gap-2">{{ __('Background') }}
                                    <template x-for="color in backgrounds" :key="color">
                                        <button type="button" x-on:click="design.background = color" class="size-5 rounded-full border border-zinc-300 ring-offset-2 transition ring-offset-white dark:border-zinc-600 dark:ring-offset-zinc-900" :class="design?.background === color ? 'ring-2 ring-zinc-900 dark:ring-white' : 'hover:scale-110'" :style="`background:${color}`" :title="color"></button>
                                    </template>
                                </span>
                            </div>

                            <template x-for="(block, index) in design?.blocks ?? []" :key="block.uid">
                                <div class="group rounded-xl border border-zinc-200 bg-white p-3 transition hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="text-xs font-semibold tracking-wide text-zinc-500 uppercase" x-text="@js($blockTypes)[block.type]"></span>
                                        <span class="flex-1"></span>
                                        <template x-if="['heading', 'text', 'button'].includes(block.type)">
                                            <span class="inline-flex gap-0.5 rounded-md bg-zinc-100 p-0.5 dark:bg-white/5">
                                                @foreach (['left' => '⟸', 'center' => '⟺', 'right' => '⟹'] as $value => $glyph)
                                                    <button type="button" x-on:click="block.align = '{{ $value }}'" class="rounded px-1.5 text-[11px] leading-5" :class="block.align === '{{ $value }}' ? 'bg-white text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-white' : 'text-zinc-400 hover:text-zinc-700'" title="{{ ['left' => __('Align left'), 'center' => __('Align center'), 'right' => __('Align right')][$value] }}">{{ ['left' => __('Align left'), 'center' => __('Align center'), 'right' => __('Align right')][$value] }}</button>
                                                @endforeach
                                            </span>
                                        </template>
                                        <button type="button" x-on:click="move(index, -1)" :disabled="index === 0" class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 disabled:opacity-30 dark:hover:bg-white/5" title="{{ __('Move up') }}"><x-ui.icon name="chevron-down" class="size-3.5 rotate-180" /></button>
                                        <button type="button" x-on:click="move(index, 1)" :disabled="index === design.blocks.length - 1" class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 disabled:opacity-30 dark:hover:bg-white/5" title="{{ __('Move down') }}"><x-ui.icon name="chevron-down" class="size-3.5" /></button>
                                        <button type="button" x-on:click="remove(index)" class="rounded p-1 text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" title="{{ __('Remove') }}"><x-ui.icon name="trash" class="size-3.5" /></button>
                                    </div>
                                    <template x-if="block.type === 'heading'">
                                        <input x-model="block.text" class="form-control text-base font-semibold" placeholder="{{ __('Title') }}">
                                    </template>
                                    <template x-if="block.type === 'text'">
                                        <textarea x-model="block.text" rows="3" class="form-control resize-y" placeholder="{{ __('Your text') }}"></textarea>
                                    </template>
                                    <template x-if="block.type === 'image'">
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <input x-model="block.src" type="url" class="form-control sm:col-span-2" placeholder="{{ __('Image link (https://…)') }}">
                                            <input x-model="block.alt" class="form-control" placeholder="{{ __('Description (alt)') }}">
                                            <input x-model="block.href" type="url" class="form-control" placeholder="{{ __('Link when clicked (optional)') }}">
                                            <label class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300"><input type="checkbox" x-model="block.full" class="form-checkbox rounded">{{ __('Full width') }}</label>
                                        </div>
                                    </template>
                                    <template x-if="block.type === 'button'">
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <input x-model="block.text" class="form-control" placeholder="{{ __('Button text') }}">
                                            <input x-model="block.href" class="form-control" placeholder="https://">
                                        </div>
                                    </template>
                                    <template x-if="block.type === 'spacer'">
                                        <span class="inline-flex gap-0.5 rounded-md bg-zinc-100 p-0.5 text-xs dark:bg-white/5">
                                            @foreach (['small' => __('Small'), 'large' => __('Large')] as $value => $label)
                                                <button type="button" x-on:click="block.size = '{{ $value }}'" class="rounded px-2 py-0.5" :class="block.size === '{{ $value }}' ? 'bg-white text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-white' : 'text-zinc-500'">{{ $label }}</button>
                                            @endforeach
                                        </span>
                                    </template>
                                    <template x-if="block.type === 'divider'">
                                        <div class="border-t border-zinc-200 dark:border-zinc-700"></div>
                                    </template>
                                </div>
                            </template>

                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="mr-1 text-xs font-medium text-zinc-500">{{ __('Add') }}</span>
                                @foreach ($blockTypes as $type => $label)
                                    <button type="button" x-on:click="add('{{ $type }}')" class="inline-flex items-center gap-1 rounded-md border border-dashed border-zinc-300 px-2.5 py-1 text-xs font-medium text-zinc-600 transition hover:border-brand-500 hover:text-brand-700 dark:border-zinc-700 dark:text-zinc-300 dark:hover:text-brand-300"><x-ui.icon name="plus" class="size-3" />{{ $label }}</button>
                                @endforeach
                            </div>
                            <input x-model="design.footer" class="form-control text-sm" placeholder="{{ __('Footer: address, legal notice… (optional)') }}" x-show="design">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{!! __('In the texts: :bold, :link.', ['bold' => '<code class="font-mono">**'.e(__('bold')).'**</code>', 'link' => '<code class="font-mono">['.e(__('text')).'](https://…)</code>']) !!}</p>
                        </div>

                        {{-- HTML --}}
                        <div x-show="mode === 'html'" x-cloak class="space-y-1.5">
                            <div class="flex justify-end" x-show="!emailBody.trim()">
                                <button type="button" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300" x-on:click="emailBody = starterEmail">{{ __('Start from a layout') }}</button>
                            </div>
                            <textarea id="email_body" x-model="emailBody" rows="14" spellcheck="false" class="form-control resize-y font-mono text-[12.5px] leading-relaxed" placeholder="{{ '<p>'.__('Hello').' '.$open.' tags.first_name | default: "'.__('there').'" '.$close.'</p>' }}"></textarea>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{!! __('Variables: :vars. An unsubscribe link is added at the bottom unless you place :unsubscribe yourself.', ['vars' => collect(['email', 'external_id', 'tags.first_name', 'tags.plan | default: "free"'])->map($variable)->implode(', '), 'unsubscribe' => $variable('unsubscribe_url')]) !!}</p>
                    </div>
                </div>
            </x-ui.card>

            {{-- WhatsApp --}}
            <x-ui.card class="composer-step" :title="__('WhatsApp')" icon="chat" x-show="channel === 'whatsapp'" x-cloak>
                <div class="space-y-4" x-data="whatsAppComposer(@js($config['whatsapp']), @js($project->credential('whatsapp')?->isUsable() ? route('projects.whatsapp.templates', $project) : null))">
                    <input type="hidden" name="whatsapp_mode" :value="mode">
                    <div class="grid grid-cols-2 gap-1 rounded-lg bg-zinc-100 p-1 text-sm dark:bg-white/5">
                        @foreach (['template' => __('Approved template'), 'text' => __('Free text')] as $key => $label)
                            <button type="button" x-on:click="mode = '{{ $key }}'" class="rounded-md py-1.5 font-medium transition" :class="mode === '{{ $key }}' ? 'bg-white text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'">{{ $label }}</button>
                        @endforeach
                    </div>

                    <div x-show="mode === 'template'" class="space-y-4">
                        <input type="hidden" name="whatsapp_template" :value="template">
                        <input type="hidden" name="whatsapp_preview" :value="preview">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Required to write first or after 24 hours without a reply: templates are approved by Meta in the WhatsApp Manager.') }}</p>
                        <p x-show="loading" class="text-sm text-zinc-400">{{ __('Loading…') }}</p>
                        <p x-show="error" x-cloak class="text-sm text-red-600 dark:text-red-400" x-text="error"></p>
                        <p x-show="!loading && !error && templates.length === 0" x-cloak class="text-sm text-zinc-500">{{ __('No approved template yet (or the business account id is missing in the settings).') }}</p>
                        <div class="grid gap-2" x-show="templates.length">
                            <template x-for="item in templates" :key="item.name + '|' + item.language">
                                <button type="button" x-on:click="pick(item)" class="rounded-lg border p-3 text-left transition"
                                    :class="template === item.name + '|' + item.language ? 'border-brand-500 bg-brand-50/60 dark:border-brand-500/60 dark:bg-brand-500/10' : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600'">
                                    <span class="flex items-center gap-2 text-sm font-medium text-zinc-900 dark:text-white"><span class="font-mono" x-text="item.name"></span><span class="rounded bg-zinc-100 px-1.5 text-[11px] text-zinc-500 dark:bg-white/10" x-text="item.language"></span><span class="text-[11px] text-zinc-400 lowercase" x-text="item.category"></span></span>
                                    <span class="mt-1 line-clamp-2 block text-xs text-zinc-500 dark:text-zinc-400" x-text="item.body"></span>
                                </button>
                            </template>
                        </div>
                        <template x-if="current && current.variables.length">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <template x-for="name in current.variables" :key="name">
                                    <div>
                                        <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" x-text="'{' + '{' + name + '}' + '}'"></label>
                                        <input class="form-control mt-1.5" :name="`whatsapp_variables[${name}]`" x-model="variables[name]" placeholder="{{ $open }} tags.first_name {{ $close }}">
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div x-show="mode === 'text'" x-cloak class="space-y-1.5">
                        <textarea name="whatsapp_body" x-model="body" rows="5" maxlength="4096" class="form-control resize-y" placeholder="{{ __('Hello').' '.$open.' tags.first_name '.$close.', …' }}"></textarea>
                        <p class="text-xs text-amber-700 dark:text-amber-400">{{ __('Only delivered to people who wrote to your number in the last 24 hours (WhatsApp rule).') }}</p>
                    </div>
                </div>
            </x-ui.card>

            {{-- SMS --}}
            <x-ui.card class="composer-step" :title="__('SMS')" icon="message" x-show="channel === 'sms'" x-cloak>
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between gap-3">
                        <label for="sms_body" class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Text') }}</label>
                        <span class="text-xs tabular-nums" :class="smsSegments > 3 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-400'"
                            x-text="smsBody.length + ' · ' + (smsSegments <= 1 ? @js(__('1 SMS')) : @js(__(':count SMS')).replace(':count', smsSegments))"></span>
                    </div>
                    <textarea id="sms_body" name="sms_body" x-model="smsBody" rows="5" maxlength="1600" :required="channel === 'sms'" class="form-control resize-y" placeholder="{{ __('Hello').' '.$open.' tags.first_name | default: "'.__('there').'" '.$close.', …' }}"></textarea>
                    @if ($project->credential('sms')?->isUsable())
                        <div x-data="smsBalance(@js(route('projects.sms.balance', $project)))" x-show="balance?.label" x-cloak class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg bg-zinc-50 px-3 py-2 text-xs dark:bg-white/5">
                            <x-ui.icon name="message" class="size-3.5 text-zinc-400" />
                            <span class="font-medium text-zinc-700 dark:text-zinc-200" x-text="balance?.label"></span>
                            {{-- Orange counts units: warn when the audience needs more. --}}
                            <span x-show="balance?.units !== null && estimate.total !== null && estimate.total * Math.max(1, smsSegments) > balance?.units" class="font-medium text-amber-700 dark:text-amber-400"
                                x-text="@js(__('Not enough for :count SMS')).replace(':count', ((estimate.total ?? 0) * Math.max(1, smsSegments)).toLocaleString('fr-FR'))"></span>
                        </div>
                    @endif
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('160 characters per SMS, 70 with accents outside the GSM alphabet or emoji: each part is billed by your provider.') }} {!! __('Variables: :vars.', ['vars' => collect(['phone_number', 'external_id', 'tags.first_name'])->map($variable)->implode(', ')]) !!}</p>
                </div>
            </x-ui.card>

            {{-- Message --}}
            <x-ui.card class="composer-step" :title="__('Message')" icon="message" x-show="channel === 'push'">
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
                            <textarea :id="`body-${index}`" :name="`texts[${index}][body]`" x-model="text.body" rows="4" maxlength="4000" :required="channel === 'push'" class="form-control resize-y" placeholder="{{ __('What do you want to say?') }}"></textarea>
                        </div>
                    </div>
                </template>
                <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Each device receives the text in its language; the others get the first one.') }}</p>
            </x-ui.card>

            {{-- Media and actions --}}
            <x-ui.card class="composer-step" :title="__('Image and actions')" icon="image" x-show="channel === 'push'">
                <div class="space-y-5">
                    <x-ui.input name="big_picture" type="url" :label="__('Image')" placeholder="https://…/image.jpg" x-model="image" :hint="__('Large image of the notification (JPEG or PNG, ideally 2:1).')" optional />
                    <x-ui.input name="icon" type="url" :label="__('Icon')" placeholder="https://…/logo.png" x-model="icon" :hint="$project->icon_path ? __('Logo next to the text on Android and the web (square PNG). By default, the icon of the project. iOS always shows the icon of the app.') : __('Logo next to the text on Android and the web (square PNG). iOS always shows the icon of the app; add an icon to the project to use it by default.')" optional />
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

            {{-- A/B test --}}
            @unless ($templateMode)
                <div x-show="channel === 'push'">
                    <x-ui.card class="composer-step" :title="__('A/B test')" :description="__('Try two versions on a sample of the audience, then send the best one to the rest.')" icon="activity">
                        <x-slot:actions>
                            <x-ui.toggle name="ab_enabled" x-model="ab" :checked="(bool) old('ab_enabled')" :label="__('Enable')" />
                        </x-slot:actions>
                        <div x-show="ab" x-collapse x-cloak class="space-y-5">
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Version A is the message above. Version B changes its title and text; everything else is shared.') }}</p>
                            <x-ui.input name="ab_title" :label="__('Title of version B')" :value="old('ab_title')" maxlength="200" optional />
                            <x-ui.textarea name="ab_body" :label="__('Text of version B')" rows="3" maxlength="1000" :value="old('ab_body')" />
                            <x-ui.select name="ab_sample" :label="__('Sample')" :value="old('ab_sample', 20)"
                                :options="collect(App\Actions\RunAbTest::SAMPLES)->mapWithKeys(fn ($p) => [$p => __(':percent % of the audience (:half % for each version)', ['percent' => $p, 'half' => $p / 2])])->all()"
                                :hint="__('Sent right away. The rest of the audience waits for your choice of the winner.')" />
                        </div>
                    </x-ui.card>
                </div>
            @endunless

            {{-- Delivery --}}
            <x-ui.card class="composer-step" :title="$templateMode ? __('Options') : __('Delivery')" icon="clock">
                @unless ($templateMode)
                <div class="grid gap-2">
                    @foreach (['now' => [__('Send now'), __('Delivered within seconds.')], 'later' => [__('Schedule'), __('At a date and time of your choice.')], 'timezone' => [__('Local time'), __('At the same hour for everyone, in their time zone.')], 'smart' => [__('Smart time'), __('Each person at the hour they usually use the app, within 24 hours.')]] as $key => [$label, $text])
                        <label class="flex cursor-pointer gap-3 rounded-lg border border-zinc-200 p-3.5 transition has-checked:border-brand-500 has-checked:bg-brand-50/50 dark:border-zinc-800 dark:has-checked:bg-brand-500/5">
                            <input type="radio" name="schedule" value="{{ $key }}" x-model="schedule" class="mt-0.5">
                            <span><span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ $label }}</span><span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $text }}</span></span>
                        </label>
                    @endforeach
                </div>
                <div x-show="schedule === 'later'" x-cloak class="mt-5">
                    <x-ui.input name="send_at" type="datetime-local" min="today" :label="__('Date and time')" :hint="__('In your time zone.')" />
                </div>
                <div x-show="schedule === 'smart'" x-cloak class="mt-4 flex items-start gap-2 rounded-lg bg-zinc-50 p-3 text-sm text-zinc-600 dark:bg-white/5 dark:text-zinc-300">
                    <x-ui.icon name="sparkles" class="mt-0.5 size-4 shrink-0 text-brand-600" />
                    <span>
                        {{ __('Learned from the sessions, clicks and events of each person. Without history, the busiest hour of your audience is used.') }}
                        @if (($busiestHour ?? null) !== null)
                            <strong class="font-semibold text-zinc-900 dark:text-white">{{ __('Busiest hour:') }} <span x-text="new Date(@js(now()->utc()->setTime($busiestHour, 0)->toIso8601String())).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })"></span></strong>
                        @endif
                    </span>
                </div>
                <div x-show="schedule === 'timezone'" x-cloak class="mt-5 max-w-xs">
                    <x-ui.input name="delivery_time" type="time" :value="old('delivery_time', $source?->option('delivery_time_of_day') ?? '09:00')" :label="__('Time of day')" :hint="__('Each device receives it at this time in its own time zone, within the next 24 hours.')" />
                </div>
                @endunless

                <button type="button" @class(['flex items-center gap-2 text-sm font-medium text-zinc-600 dark:text-zinc-400', 'mt-5' => ! $templateMode]) x-on:click="advanced = !advanced">
                    <x-ui.icon name="chevron-right" class="size-4 text-zinc-400 transition" ::class="advanced && 'rotate-90'" />{{ __('Advanced options') }}
                </button>
                <div x-show="advanced" x-collapse x-cloak>
                    <div class="grid gap-5 pt-5 sm:grid-cols-2">
                        <div class="contents" x-show="channel === 'push'">
                        <x-ui.select name="priority" :label="__('Priority')" :value="old('priority', $source?->option('priority', 10))" :options="[10 => __('High (immediate)'), 5 => __('Normal (battery friendly)')]" />
                        <x-ui.input name="ttl" type="number" min="0" max="2419200" :label="__('Time to live (seconds)')" :value="old('ttl', $source?->option('ttl'))" placeholder="{{ $project->setting('default_ttl') ?? 259200 }}" optional />
                        <x-ui.input name="collapse_id" :label="__('Collapse id')" :value="old('collapse_id', $source?->option('collapse_id'))" :hint="__('A new message with the same id replaces the previous one.')" optional />
                        <x-ui.input name="ios_badge" type="number" min="0" :label="__('iOS badge')" :value="old('ios_badge', $source?->option('ios_badgeCount'))" :hint="__('Number shown on the app icon.')" optional />
                        <x-ui.input name="ios_sound" :label="__('iOS sound')" :value="old('ios_sound', $source?->option('ios_sound'))" placeholder="default" optional />
                        <x-ui.input name="android_channel_id" :label="__('Android channel')" :value="old('android_channel_id', $source?->option('android_channel_id'))" optional />
                        <x-ui.input name="thread_id" :label="__('Thread (iOS grouping)')" :value="old('thread_id', $source?->option('thread_id'))" optional />
                        </div>
                        @unless ($templateMode)
                            <x-ui.input name="name" :label="__('Internal name')" :value="old('name', $source instanceof \App\Models\Message ? $source->name : null)" :hint="__('Only shown in the dashboard.')" optional />
                            <x-ui.input name="labels" :label="__('Labels')" :value="old('labels', $source instanceof \App\Models\Message ? implode(', ', $source->labels ?? []) : null)" :placeholder="__('e.g. promo, newsletter')" :hint="__('To sort and filter the messages. Separated by commas.')" optional />
                        @endunless
                    </div>
                </div>
            </x-ui.card>
        </div>

        {{-- Preview and send --}}
        <aside class="space-y-4 lg:sticky lg:top-6">
            <x-ui.card :title="__('Preview')" icon="smartphone" x-show="channel === 'push'">
                <x-messages.preview :project="$project" />
            </x-ui.card>
                <x-ui.card :title="__('Preview')" icon="chat" x-show="channel === 'whatsapp'" x-cloak>
                    <div class="rounded-2xl bg-[#efe7dd] p-4 dark:bg-[#0b141a]">
                        <p class="ml-auto max-w-[85%] rounded-xl rounded-tr-sm bg-[#d9fdd3] px-3 py-2 text-sm break-words whitespace-pre-line text-zinc-900 shadow-sm dark:bg-[#005c4b] dark:text-zinc-100" x-text="whatsAppPreview"></p>
                    </div>
                </x-ui.card>
                <x-ui.card :title="__('Preview')" icon="message" x-show="channel === 'sms'" x-cloak>
                    <div class="rounded-2xl bg-zinc-100 p-4 dark:bg-white/5">
                        <p class="text-center text-[11px] text-zinc-400">{{ $project->credential('sms')?->detail('from') ?? $project->credential('sms')?->detail('sender_name') ?? $project->credential('sms')?->detail('sender_address') ?? 'SMS' }}</p>
                        <p class="mt-2 max-w-[85%] rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 text-sm break-words whitespace-pre-line text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-zinc-100" x-text="smsPreview"></p>
                    </div>
                </x-ui.card>
                <x-ui.card :title="__('Preview')" icon="mail" x-show="channel === 'email'" x-cloak :padding="false">
                    <div class="border-y border-zinc-100 px-5 py-3 text-sm dark:border-zinc-800">
                        <p class="truncate font-semibold text-zinc-900 dark:text-white" x-text="emailSubject || @js(__('Subject'))"></p>
                        <p class="truncate text-zinc-500" x-text="emailPreheader || @js($project->credential('email')?->detail('from_address') ?? '')"></p>
                    </div>
                    <iframe title="{{ __('Preview') }}" class="block h-96 w-full rounded-b-2xl bg-white" sandbox="" :srcdoc="emailPreview"></iframe>
                </x-ui.card>

            @if ($templateMode)
                <x-ui.button class="w-full" size="lg" icon="check">{{ $template ? __('Save the template') : __('Create the template') }}</x-ui.button>
            @else
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
                    @foreach (['ios' => 'iOS', 'android' => 'Android', 'huawei' => 'Huawei', 'amazon' => 'Amazon', 'web' => 'Web', 'email' => __('Email'), 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $key => $label)
                        <span x-show="estimate.platforms.{{ $key }}">{{ $label }} <strong class="font-semibold tabular-nums text-zinc-700 dark:text-zinc-300" x-text="format(estimate.platforms.{{ $key }})"></strong></span>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-amber-700 dark:text-amber-400" x-show="estimate.error" x-text="estimate.error" x-cloak></p>
                <p class="mt-3 text-xs text-amber-700 dark:text-amber-400" x-show="estimate.total === 0 && !estimate.error" x-cloak x-text="['sms', 'whatsapp'].includes(channel) ? @js(__('No subscribed phone number matches this audience yet.')) : channel === 'email' ? @js(__('No subscribed address matches this audience yet.')) : @js(__('No subscribed device matches this audience yet.'))">{{ __('No subscribed device matches this audience yet.') }}</p>

                <x-ui.button class="send-plane mt-5 w-full" size="lg" icon="send">
                    <span x-text="ab && channel === 'push' ? @js(__('Start the A/B test')) : (schedule !== 'now' ? @js(__('Schedule the message')) : (channel === 'email' ? @js(__('Send the email')) : (channel === 'sms' ? @js(__('Send the SMS')) : (channel === 'whatsapp' ? @js(__('Send on WhatsApp')) : @js(__('Send the message'))))))">{{ __('Send the message') }}</span>
                </x-ui.button>
                @isset($draft)
                    @if ($draft)<input type="hidden" name="draft_id" value="{{ $draft->id }}">@endif
                @endisset
                <x-ui.button type="submit" name="save_draft" value="1" variant="ghost" class="mt-2 w-full" icon="file">{{ __('Save as draft') }}</x-ui.button>
                @php($testDevices = $project->subscriptions()->where('is_test', true)->where('enabled', true)->whereNull('invalid_at')->count())
                <div x-show="! ['sms', 'whatsapp'].includes(channel)" class="mt-3 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                    <x-ui.button type="button" variant="secondary" class="w-full" icon="smartphone" x-on:click="sendTest({{ \Illuminate\Support\Js::from(route('projects.messages.test', $project)) }})" ::disabled="test.sending">
                        <span x-text="test.sending ? @js(__('Sending…')) : @js($testDevices ? trans_choice('Send a test to my device|Send a test to my :count devices', $testDevices, ['count' => $testDevices]) : __('Send a test'))">{{ __('Send a test') }}</span>
                    </x-ui.button>
                    <p class="mt-2 text-xs" x-show="test.result" x-cloak :class="test.ok ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'">
                        <span x-text="test.result"></span>
                        <a x-show="test.url" :href="test.url" class="ml-1 font-medium underline">{{ __('See the report') }}</a>
                    </p>
                    @unless ($testDevices)
                        <p class="mt-2 text-xs text-zinc-500" x-show="! test.result">{{ __('Mark your own phone as a test device in Audience to try a message before sending it to everyone.') }}</p>
                    @endunless
                </div>
            </div>
            @endif
        </aside>
    </form>
</x-layouts.app>
