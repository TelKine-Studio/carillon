@php
    $editing = $journey->exists;
    $trigger = old('trigger', $journey->trigger ?? ['type' => 'new_device']);
    $steps = old('steps', $journey->steps ?? []);
    $config = [
        'labels' => ['push' => __('Send a notification'), 'email' => __('Send an email'), 'sms' => __('Send an SMS'), 'whatsapp' => __('Send a WhatsApp message'), 'in_app' => __('Show an in-app message'), 'wait' => __('Wait'), 'wait_event' => __('Wait for an event'), 'condition' => __('Condition'), 'split' => __('A/B split'), 'exit' => __('End of the journey')],
        'texts' => [
            'units' => ['minutes' => __('minutes'), 'hours' => __('hours'), 'days' => __('days')],
            'exit' => __('leave the journey'), 'next' => __('next step'), 'step' => __('step :number'),
            'yes' => __('Yes'), 'no' => __('No'), 'from_template' => __('From a template'), 'empty' => __('Not written yet'),
            'wait_for' => __('Wait :duration'), 'wait_event' => __('Until :event, at most :duration'),
            'if_tag' => __(':key is :value'), 'if_not_tag' => __(':key is not :value'), 'any' => __('set'),
            'clicked' => __('Clicked a message of the journey'), 'not_clicked' => __('Did not click'),
            'did' => __('Did :event'), 'did_not' => __('Did not do :event'),
            'no_in_app' => __('Choose an in-app message'), 'exit_text' => __('The person completes the journey here.'), 'template' => __('Template :name'),
        ],
        'inApps' => $inAppMessages->mapWithKeys(fn ($m) => [$m->id => $m->name])->all(),
        'trigger' => ['type' => $trigger['type'] ?? 'new_device', 'days' => $trigger['days'] ?? 7, 'key' => $trigger['key'] ?? '', 'value' => $trigger['value'] ?? '', 'event' => $trigger['event'] ?? ''],
        'steps' => collect($steps)->map(fn ($s, $i) => [
            'uid' => $i + 1,
            'type' => $s['type'] ?? 'push',
            'amount' => $s['amount'] ?? 1,
            'unit' => $s['unit'] ?? 'hours',
            'template_id' => $s['template_id'] ?? '',
            'title' => $s['title'] ?? '',
            'subject' => $s['subject'] ?? '',
            'body' => $s['body'] ?? '',
            'url' => $s['url'] ?? '',
            'key' => $s['key'] ?? '',
            'operator' => $s['operator'] ?? 'is',
            'value' => $s['value'] ?? '',
            'check' => $s['check'] ?? 'tag',
            'event' => $s['event'] ?? '',
            'hours' => $s['hours'] ?? '',
            'otherwise' => $s['otherwise'] ?? 'exit',
            'goto' => isset($s['else_step']) ? $s['else_step'] + 1 : ($s['goto'] ?? ''),
            'on_timeout' => $s['on_timeout'] ?? 'exit',
            'percent' => $s['percent'] ?? 50,
            'b_target' => $s['b_target'] ?? 'goto',
            // Jumps point at a step (its uid), not at a number: inserting or moving steps keeps them right.
            'goto_uid' => isset($s['else_step']) ? $s['else_step'] + 1 : (filled($s['goto'] ?? null) ? (int) $s['goto'] : null),
            'b_goto_uid' => isset($s['b_step']) ? $s['b_step'] + 1 : (filled($s['b_goto'] ?? null) ? (int) $s['b_goto'] : null),
            'whatsapp_template' => $s['whatsapp_template'] ?? '',
            'whatsapp_language' => $s['whatsapp_language'] ?? 'fr',
            'whatsapp_variables' => $s['whatsapp_variables'] ?? '',
            'in_app_message_id' => $s['in_app_message_id'] ?? '',
            'open' => false,
        ])->values()->all(),
    ];
    // Written in two parts: Blade would read the braces as an echo.
    $tagExample = '{'.'{ tags.first_name | default: "…" }'.'}';
    $emailTemplates = $templates->filter(fn ($t) => $t->option('target_channel') === 'email');
    $pushTemplates = $templates->filter(fn ($t) => ! in_array($t->option('target_channel'), ['email', 'sms'], true));
    $templateOptions = ['' => __('Write the message here')] + $pushTemplates->mapWithKeys(fn ($t) => [$t->id => $t->name])->all();
    $emailTemplateOptions = ['' => __('Write the email here')] + $emailTemplates->mapWithKeys(fn ($t) => [$t->id => $t->name])->all();
    // Step kinds, grouped like the block menu of OneSignal: [icon, label, colors of the tile].
    $kinds = [
        'push' => ['bell', __('Notification'), 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300'],
        'email' => ['mail', __('Email'), 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300'],
        'sms' => ['message', 'SMS', 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300'],
        'whatsapp' => ['chat', 'WhatsApp', 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-300'],
        'in_app' => ['image', __('In-app message'), 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300'],
        'wait' => ['clock', __('Wait'), 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300'],
        'wait_event' => ['activity', __('Wait for an event'), 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300'],
        'condition' => ['sliders', __('Condition'), 'bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300'],
        'split' => ['columns', __('A/B split'), 'bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300'],
        'exit' => ['check-circle', __('End of the journey'), 'bg-zinc-100 text-zinc-600 dark:bg-white/5 dark:text-zinc-300'],
    ];
    $groups = [__('Messages') => ['push', 'email', 'sms', 'whatsapp', 'in_app'], __('Timing') => ['wait', 'wait_event'], __('Branches') => ['condition', 'split', 'exit']];
    $config['kinds'] = collect($kinds)->map(fn ($k) => ['icon' => $k[0], 'tile' => $k[2]])->all();
@endphp
<x-layouts.app :title="($editing ? $journey->name : __('New journey')).' · '.$project->name">
    <x-ui.page-header :title="$editing ? $journey->name : __('New journey')" :description="__('People enter when the trigger happens, then go through the steps from top to bottom.')">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.journeys.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Journeys') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span>{{ $editing ? __('Edit') : __('New') }}</span>
        </x-slot:breadcrumb>
    </x-ui.page-header>

    <form method="POST" action="{{ $editing ? route('projects.journeys.update', [$project, $journey]) : route('projects.journeys.store', $project) }}"
        x-data="journeyEditor(@js($config))" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="min-w-0 space-y-6">
            <x-ui.card :title="__('Name')" icon="pencil">
                <x-ui.input name="name" :value="old('name', $journey->name)" required maxlength="120" :placeholder="__('e.g. Welcome')" />
            </x-ui.card>

            {{-- Trigger --}}
            <x-ui.card :title="__('Trigger')" :description="__('When does a person enter the journey?')" icon="zap">
                <input type="hidden" name="trigger[type]" :value="trigger.type">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'new_device' => ['smartphone', __('A device subscribes'), __('Right after the app is installed and allowed to notify.')],
                        'inactive' => ['clock', __('Inactivity'), __('The person has not opened the app for a number of days.')],
                        'tag' => ['file', __('A tag changes'), __('Your app or server sets a tag, for example plan = premium.')],
                        'event' => ['activity', __('An event'), __('Your app or server reports an event, for example purchase.')],
                    ] as $type => [$icon, $label, $text])
                        <button type="button" x-on:click="trigger.type = '{{ $type }}'"
                            class="flex gap-3 rounded-xl border p-4 text-left transition"
                            :class="trigger.type === '{{ $type }}' ? 'border-brand-500 bg-brand-50/50 ring-4 ring-brand-500/10 dark:bg-brand-500/10' : 'border-zinc-200 hover:border-zinc-300 dark:border-zinc-800 dark:hover:border-zinc-700'">
                            <x-ui.icon :name="$icon" class="mt-0.5 size-5 shrink-0 text-brand-600 dark:text-brand-300" />
                            <span><span data-trigger-label="{{ $type }}" class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ $label }}</span><span class="mt-0.5 block text-sm text-zinc-500 dark:text-zinc-400">{{ $text }}</span></span>
                        </button>
                    @endforeach
                </div>
                <div class="mt-5" x-show="trigger.type === 'inactive'" x-cloak>
                    <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" for="trigger-days">{{ __('Inactive for (days)') }}</label>
                    <input id="trigger-days" type="number" min="1" max="365" name="trigger[days]" x-model="trigger.days" class="form-control mt-1.5 w-32" :disabled="trigger.type !== 'inactive'">
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2" x-show="trigger.type === 'tag'" x-cloak>
                    <div><label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Tag') }}</label><input name="trigger[key]" x-model="trigger.key" class="form-control mt-1.5 font-mono" placeholder="plan" :disabled="trigger.type !== 'tag'"></div>
                    <div><label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Value') }} <span class="font-normal text-zinc-400">{{ __('(empty: any value)') }}</span></label><input name="trigger[value]" x-model="trigger.value" class="form-control mt-1.5 font-mono" placeholder="premium" :disabled="trigger.type !== 'tag'"></div>
                </div>
                <div class="mt-5" x-show="trigger.type === 'event'" x-cloak>
                    <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Event name') }}</label>
                    <input name="trigger[event]" x-model="trigger.event" class="form-control mt-1.5 font-mono sm:w-72" placeholder="purchase" :disabled="trigger.type !== 'event'">
                </div>
                @error('trigger.days')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('trigger.key')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('trigger.event')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </x-ui.card>

            {{-- Steps, drawn as a flow like the journeys of OneSignal: a node per step, "+" between them. --}}
            <section>
                <div class="mb-3 flex items-baseline justify-between gap-3">
                    <h2 class="text-[15px] font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Steps') }}</h2>
                    <p class="text-xs text-zinc-500">{{ __('Click a step to edit it.') }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-[radial-gradient(circle,var(--color-zinc-200)_1px,transparent_1px)] [background-size:16px_16px] px-3 py-6 sm:px-6 dark:border-zinc-800 dark:bg-[radial-gradient(circle,var(--color-zinc-800)_1px,transparent_1px)]">
                    <div class="mx-auto max-w-2xl">
                        {{-- Start --}}
                        <div class="mx-auto flex w-fit items-center gap-2 rounded-full border border-brand-200 bg-white px-3.5 py-1.5 text-sm font-medium text-zinc-900 shadow-(--shadow-soft) dark:border-brand-500/30 dark:bg-zinc-900 dark:text-white">
                            <span class="inline-flex size-6 items-center justify-center rounded-full bg-brand-600 text-white"><x-ui.icon name="zap" class="size-3.5" /></span>
                            <span x-text="triggerSummary()"></span>
                        </div>

                        <template x-for="(step, index) in steps" :key="step.uid">
                            <div>
                                {{-- Connector with the insert button --}}
                                <div class="relative flex h-12 justify-center" x-data="{ menu: false }" x-on:click.outside="menu = false">
                                    <span class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-zinc-300 dark:bg-zinc-700"></span>
                                    <button type="button" x-on:click="menu = ! menu" class="relative z-10 my-auto inline-flex size-6 items-center justify-center rounded-full border border-zinc-300 bg-white text-zinc-500 transition hover:border-brand-500 hover:text-brand-600 dark:border-zinc-700 dark:bg-zinc-900" title="{{ __('Insert a step here') }}"><x-ui.icon name="plus" class="size-3.5" /></button>
                                    @include('journeys.partials.step-menu', ['at' => 'index'])
                                </div>

                                {{-- Node --}}
                                <div class="card overflow-hidden transition" :class="step.open && 'ring-2 ring-brand-500/40'">
                                    <input type="hidden" :name="`steps[${index}][type]`" :value="step.type">
                                    <div class="flex cursor-pointer items-center gap-3 px-4 py-3" x-on:click="step.open = ! step.open">
                                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg" :class="kinds[step.type].tile">
                                            @foreach ($kinds as $kind => [$icon])
                                                <span x-show="step.type === '{{ $kind }}'" class="contents"><x-ui.icon :name="$icon" class="size-4" /></span>
                                            @endforeach
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white"><span class="text-xs font-medium text-zinc-400 tabular-nums" x-text="index + 1"></span><span x-text="labels[step.type]"></span></span>
                                            <span class="block text-xs break-words text-zinc-500" x-text="summary(step)"></span>
                                        </span>
                                        <span class="flex shrink-0 items-center" x-on:click.stop>
                                            <button type="button" class="rounded-md p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 disabled:opacity-30 dark:hover:bg-white/5" x-on:click="move(index, -1)" :disabled="index === 0" title="{{ __('Move up') }}"><x-ui.icon name="chevron-down" class="size-4 rotate-180" /></button>
                                            <button type="button" class="rounded-md p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 disabled:opacity-30 dark:hover:bg-white/5" x-on:click="move(index, 1)" :disabled="index === steps.length - 1" title="{{ __('Move down') }}"><x-ui.icon name="chevron-down" class="size-4" /></button>
                                            <button type="button" class="rounded-md p-1.5 text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" x-on:click="remove(index)" title="{{ __('Remove') }}"><x-ui.icon name="trash" class="size-4" /></button>
                                        </span>
                                    </div>

                                    {{-- Branches of a condition or a split --}}
                                    <template x-if="step.type === 'condition' || step.type === 'split'">
                                        <div class="flex flex-wrap gap-2 border-t border-dashed border-zinc-200 px-4 py-2.5 text-xs dark:border-zinc-800">
                                            <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-1 font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                                <span x-text="step.type === 'split' ? `A · ${step.percent} %` : texts.yes"></span><x-ui.icon name="arrow-right" class="size-3 rotate-90" /><span x-text="index === steps.length - 1 ? texts.exit : texts.next"></span>
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 rounded-md bg-zinc-100 px-2 py-1 font-medium text-zinc-700 dark:bg-white/5 dark:text-zinc-300">
                                                <span x-text="step.type === 'split' ? `B · ${100 - step.percent} %` : texts.no"></span><x-ui.icon name="arrow-right" class="size-3" /><span x-text="branchTarget(step)"></span>
                                            </span>
                                        </div>
                                    </template>

                                    {{-- Settings of the step --}}
                                    <div x-show="step.open" x-collapse class="border-t border-zinc-100 bg-zinc-50/50 px-4 py-4 dark:border-zinc-800 dark:bg-white/[.02]">
                                        @include('journeys.partials.step-fields')
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- End, with the last insert button --}}
                        <div class="relative flex h-12 justify-center" x-data="{ menu: false }" x-on:click.outside="menu = false">
                            <span class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-zinc-300 dark:bg-zinc-700"></span>
                            <button type="button" x-on:click="menu = ! menu" class="relative z-10 my-auto inline-flex size-6 items-center justify-center rounded-full border border-zinc-300 bg-white text-zinc-500 transition hover:border-brand-500 hover:text-brand-600 dark:border-zinc-700 dark:bg-zinc-900" title="{{ __('Add a step') }}"><x-ui.icon name="plus" class="size-3.5" /></button>
                            @include('journeys.partials.step-menu', ['at' => 'steps.length'])
                        </div>
                        <div class="mx-auto flex w-fit items-center gap-2 rounded-full border border-zinc-200 bg-white px-3.5 py-1.5 text-sm text-zinc-500 dark:border-zinc-800 dark:bg-zinc-900"><x-ui.icon name="check-circle" class="size-4" />{{ __('End of the journey') }}</div>
                    </div>
                </div>
                @foreach ($errors->get('steps*') as $messages)
                    @foreach ($messages as $message)<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@endforeach
                @endforeach
            </section>
        </div>

        <aside class="space-y-6 lg:sticky lg:top-8">
            <x-ui.card :title="__('Who')" icon="users">
                <div class="space-y-5">
                    <x-ui.select name="segment" :label="__('Only people of the segment')" :value="old('segment', $journey->segment)"
                        :options="['' => __('Everybody')] + collect(App\Models\Segment::BUILT_IN)->reject(fn ($s) => in_array($s, ['All', 'Subscribed Users'], true))->mapWithKeys(fn ($s) => [$s => __($s)])->all() + $segments->mapWithKeys(fn ($s) => [$s => $s])->all()" />
                    <x-ui.toggle name="reentry" :checked="(bool) old('reentry', $journey->reentry)" :label="__('Can enter again')" :description="__('Once out, a person can go through it again when the trigger happens again.')" />
                </div>
            </x-ui.card>
            @php($window = $journey->send_window)
            <x-ui.card :title="__('Sending hours')" icon="clock" x-data="{ window: {{ old('window_enabled', $window ? 1 : 0) ? 'true' : 'false' }} }">
                <div class="space-y-4">
                    <x-ui.toggle name="window_enabled" x-model="window" :checked="(bool) old('window_enabled', (bool) $window)" :label="__('Only at certain hours')" :description="__('Messages due outside these hours wait for the next opening, in the time zone of each person.')" />
                    <div x-show="window" x-cloak class="grid grid-cols-2 gap-3">
                        <x-ui.select name="window_from" size="sm" :label="__('From')" :value="old('window_from', $window['from'] ?? 9)" :options="collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => sprintf('%02d:00', $h)])->all()" />
                        <x-ui.select name="window_to" size="sm" :label="__('To')" :value="old('window_to', $window['to'] ?? 21)" :options="collect(range(0, 23))->mapWithKeys(fn ($h) => [$h => sprintf('%02d:00', $h)])->all()" />
                    </div>
                </div>
            </x-ui.card>
            <div class="card p-5">
                <x-ui.button class="w-full" icon="check">{{ $editing ? __('Save the journey') : __('Create the journey') }}</x-ui.button>
                <p class="mt-3 text-xs text-zinc-500">{{ $editing ? __('People already in the journey continue from the step they are at.') : __('It is saved as a draft: nothing is sent until you activate it.') }}</p>
            </div>
        </aside>
    </form>
</x-layouts.app>
