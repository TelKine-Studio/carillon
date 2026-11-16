@php
    $editing = $journey->exists;
    $trigger = old('trigger', $journey->trigger ?? ['type' => 'new_device']);
    $steps = old('steps', $journey->steps ?? []);
    $config = [
        'labels' => ['push' => __('Send a notification'), 'email' => __('Send an email'), 'wait' => __('Wait'), 'condition' => __('Tag condition')],
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
        ])->values()->all(),
    ];
    // Written in two parts: Blade would read the braces as an echo.
    $tagExample = '{'.'{ tags.first_name | default: "…" }'.'}';
    [$emailTemplates, $pushTemplates] = $templates->partition(fn ($t) => $t->option('target_channel') === 'email');
    $templateOptions = ['' => __('Write the message here')] + $pushTemplates->mapWithKeys(fn ($t) => [$t->id => $t->name])->all();
    $emailTemplateOptions = ['' => __('Write the email here')] + $emailTemplates->mapWithKeys(fn ($t) => [$t->id => $t->name])->all();
    $kinds = [
        'push' => ['bell', __('Notification')],
        'email' => ['mail', __('Email')],
        'wait' => ['clock', __('Wait')],
        'condition' => ['sliders', __('Condition')],
    ];
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
                            <span><span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ $label }}</span><span class="mt-0.5 block text-sm text-zinc-500 dark:text-zinc-400">{{ $text }}</span></span>
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

            {{-- Steps --}}
            <section>
                <h2 class="mb-3 text-[15px] font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Steps') }}</h2>
                <ol class="space-y-0">
                    <template x-for="(step, index) in steps" :key="step.uid">
                        <li>
                            <div class="mx-auto h-5 w-px bg-zinc-200 dark:bg-zinc-800" x-show="index > 0"></div>
                            <div class="card p-4 sm:p-5">
                                <input type="hidden" :name="`steps[${index}][type]`" :value="step.type">
                                <div class="mb-4 flex items-center gap-3">
                                    <span class="inline-flex size-8 items-center justify-center rounded-lg bg-zinc-100 text-xs font-bold text-zinc-600 dark:bg-white/5 dark:text-zinc-300" x-text="index + 1"></span>
                                    <span class="flex-1 text-sm font-semibold text-zinc-900 dark:text-white" x-text="labels[step.type]"></span>
                                    <button type="button" class="rounded-md p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 disabled:opacity-30 dark:hover:bg-white/5" x-on:click="move(index, -1)" :disabled="index === 0" title="{{ __('Move up') }}"><x-ui.icon name="chevron-down" class="size-4 rotate-180" /></button>
                                    <button type="button" class="rounded-md p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 disabled:opacity-30 dark:hover:bg-white/5" x-on:click="move(index, 1)" :disabled="index === steps.length - 1" title="{{ __('Move down') }}"><x-ui.icon name="chevron-down" class="size-4" /></button>
                                    <button type="button" class="rounded-md p-1.5 text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10" x-on:click="steps.splice(index, 1)" title="{{ __('Remove') }}"><x-ui.icon name="trash" class="size-4" /></button>
                                </div>

                                {{-- Wait --}}
                                <div x-show="step.type === 'wait'" class="flex flex-wrap items-center gap-3">
                                    <input type="number" min="1" max="1000" :name="`steps[${index}][amount]`" x-model="step.amount" class="form-control w-24" :disabled="step.type !== 'wait'" aria-label="{{ __('Duration') }}">
                                    <input type="hidden" :name="`steps[${index}][unit]`" :value="step.unit" :disabled="step.type !== 'wait'">
                                    <div class="inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">
                                        @foreach (['minutes' => __('minutes'), 'hours' => __('hours'), 'days' => __('days')] as $unit => $unitLabel)
                                            <button type="button" x-on:click="step.unit = '{{ $unit }}'" class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                                                :class="step.unit === '{{ $unit }}' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'">{{ $unitLabel }}</button>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Notification / email --}}
                                <div x-show="step.type === 'push' || step.type === 'email'" class="space-y-4">
                                    @if ($pushTemplates->isNotEmpty())
                                        <div x-show="step.type === 'push'">
                                            <x-ui.select name="template-choice" :options="$templateOptions" x-bind:name="`steps[${index}][template_id]`" x-model="step.template_id" x-bind:disabled="step.type !== 'push'" :label="__('Content')" />
                                        </div>
                                    @endif
                                    @if ($emailTemplates->isNotEmpty())
                                        <div x-show="step.type === 'email'">
                                            <x-ui.select name="email-template-choice" :options="$emailTemplateOptions" x-bind:name="`steps[${index}][template_id]`" x-model="step.template_id" x-bind:disabled="step.type !== 'email'" :label="__('Content')" />
                                        </div>
                                    @endif
                                    <div x-show="! step.template_id" class="space-y-4">
                                        <div x-show="step.type === 'push'"><label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Title') }} <span class="font-normal text-zinc-400">{{ __('Optional') }}</span></label><input :name="`steps[${index}][title]`" x-model="step.title" maxlength="200" class="form-control mt-1.5" :disabled="step.type !== 'push'"></div>
                                        <div x-show="step.type === 'email'"><label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Subject') }}</label><input :name="`steps[${index}][subject]`" x-model="step.subject" maxlength="200" class="form-control mt-1.5" :disabled="step.type !== 'email'"></div>
                                        <div>
                                            <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" x-text="step.type === 'email' ? @js(__('Content (HTML)')) : @js(__('Message'))"></label>
                                            <textarea :name="`steps[${index}][body]`" x-model="step.body" :rows="step.type === 'email' ? 6 : 3" class="form-control mt-1.5" :class="step.type === 'email' && 'font-mono text-xs'"></textarea>
                                            <p class="mt-1.5 text-xs text-zinc-500">{{ __('Personalize with :example', ['example' => $tagExample]) }}</p>
                                        </div>
                                        <div x-show="step.type === 'push'"><label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Link') }} <span class="font-normal text-zinc-400">{{ __('Optional') }}</span></label><input type="url" :name="`steps[${index}][url]`" x-model="step.url" class="form-control mt-1.5" placeholder="https://" :disabled="step.type !== 'push'"></div>
                                    </div>
                                </div>

                                {{-- Condition --}}
                                <div x-show="step.type === 'condition'" class="space-y-3">
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('People who do not match leave the journey here.') }}</p>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Continue if the tag') }}</span>
                                        <input :name="`steps[${index}][key]`" x-model="step.key" class="form-control w-36 font-mono" placeholder="plan" :disabled="step.type !== 'condition'" aria-label="{{ __('Tag') }}">
                                        <input type="hidden" :name="`steps[${index}][operator]`" :value="step.operator" :disabled="step.type !== 'condition'">
                                        <div class="inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">
                                            @foreach (['is' => __('is'), 'is_not' => __('is not')] as $operator => $operatorLabel)
                                                <button type="button" x-on:click="step.operator = '{{ $operator }}'" class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                                                    :class="step.operator === '{{ $operator }}' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'">{{ $operatorLabel }}</button>
                                            @endforeach
                                        </div>
                                        <input :name="`steps[${index}][value]`" x-model="step.value" class="form-control w-36 font-mono" placeholder="{{ __('any value') }}" :disabled="step.type !== 'condition'" aria-label="{{ __('Value') }}">
                                    </div>
                                </div>
                            </div>
                        </li>
                    </template>
                </ol>

                <div class="mt-4 flex flex-wrap items-center gap-2" :class="steps.length && 'border-t border-dashed border-zinc-200 pt-4 dark:border-zinc-800'">
                    <span class="mr-1 text-sm text-zinc-500">{{ __('Add a step:') }}</span>
                    @foreach ($kinds as $kind => [$icon, $label])
                        <x-ui.button type="button" variant="secondary" size="sm" :icon="$icon" x-on:click="add('{{ $kind }}')">{{ $label }}</x-ui.button>
                    @endforeach
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
            <div class="card p-5">
                <x-ui.button class="w-full" icon="check">{{ $editing ? __('Save the journey') : __('Create the journey') }}</x-ui.button>
                <p class="mt-3 text-xs text-zinc-500">{{ $editing ? __('People already in the journey continue from the step they are at.') : __('It is saved as a draft: nothing is sent until you activate it.') }}</p>
            </div>
        </aside>
    </form>
</x-layouts.app>
