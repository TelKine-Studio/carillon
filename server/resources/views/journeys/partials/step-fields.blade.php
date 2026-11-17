{{-- Settings of one step (inside the x-for of the flow: step, index). --}}
@php
    $segment = fn (string $model, array $options, string $when) => collect($options)->map(fn ($label, $value) => '<button type="button" x-on:click="'.$model.' = \''.$value.'\'" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="'.$model.' === \''.$value.'\' ? \'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white\' : \'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white\'">'.e($label).'</button>')->implode('');
    $units = ['minutes' => __('minutes'), 'hours' => __('hours'), 'days' => __('days')];
@endphp

{{-- Wait --}}
<div x-show="step.type === 'wait'" class="flex flex-wrap items-center gap-3">
    <input type="number" min="1" max="1000" :name="`steps[${index}][amount]`" x-model="step.amount" class="form-control w-24" :disabled="step.type !== 'wait'" aria-label="{{ __('Duration') }}">
    <input type="hidden" :name="`steps[${index}][unit]`" :value="step.unit" :disabled="step.type !== 'wait'">
    <div class="inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">{!! $segment('step.unit', $units, 'wait') !!}</div>
</div>

{{-- Wait for an event --}}
<div x-show="step.type === 'wait_event'" class="space-y-3">
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Continue as soon as the person does') }}</span>
        <input :name="`steps[${index}][event]`" x-model="step.event" class="form-control w-40 font-mono" placeholder="purchase" :disabled="step.type !== 'wait_event'" aria-label="{{ __('Event name') }}">
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Waiting at most') }}</span>
        <input type="number" min="1" max="1000" :name="`steps[${index}][amount]`" x-model="step.amount" class="form-control w-24" :disabled="step.type !== 'wait_event'" aria-label="{{ __('Duration') }}">
        <input type="hidden" :name="`steps[${index}][unit]`" :value="step.unit" :disabled="step.type !== 'wait_event'">
        <div class="inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">{!! $segment('step.unit', $units, 'wait_event') !!}</div>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Then, without the event:') }}</span>
        <input type="hidden" :name="`steps[${index}][on_timeout]`" :value="step.on_timeout" :disabled="step.type !== 'wait_event'">
        <div class="inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">{!! $segment('step.on_timeout', ['exit' => __('leave the journey'), 'continue' => __('continue anyway')], 'wait_event') !!}</div>
    </div>
</div>

{{-- Notification, email, SMS --}}
<div x-show="['push', 'email', 'sms'].includes(step.type)" class="space-y-4">
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
            <textarea :name="`steps[${index}][body]`" x-model="step.body" :rows="step.type === 'email' ? 6 : 3" class="form-control mt-1.5" :class="step.type === 'email' && 'font-mono text-xs'" :disabled="! ['push', 'email', 'sms'].includes(step.type)"></textarea>
            <p class="mt-1.5 text-xs text-zinc-500">{{ __('Personalize with :example', ['example' => $tagExample]) }}</p>
        </div>
        <div x-show="step.type === 'push'"><label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Link') }} <span class="font-normal text-zinc-400">{{ __('Optional') }}</span></label><input type="url" :name="`steps[${index}][url]`" x-model="step.url" class="form-control mt-1.5" placeholder="https://" :disabled="step.type !== 'push'"></div>
    </div>
</div>

{{-- WhatsApp --}}
<div x-show="step.type === 'whatsapp'" class="space-y-4">
    @unless ($whatsapp)
        <p class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"><x-ui.icon name="alert" class="mt-0.5 size-3.5 shrink-0" />{{ __('WhatsApp is not set up for this project yet (Settings → Platforms).') }}</p>
    @endunless
    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_120px]">
        <div><label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Approved template') }}</label><input :name="`steps[${index}][whatsapp_template]`" x-model="step.whatsapp_template" class="form-control mt-1.5 font-mono" placeholder="order_update" :disabled="step.type !== 'whatsapp'"></div>
        <div><label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Language') }}</label><input :name="`steps[${index}][whatsapp_language]`" x-model="step.whatsapp_language" class="form-control mt-1.5 font-mono" placeholder="fr" :disabled="step.type !== 'whatsapp'"></div>
    </div>
    <div x-show="step.whatsapp_template"><label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Variables') }} <span class="font-normal text-zinc-400">{{ __('Optional') }}</span></label><input :name="`steps[${index}][whatsapp_variables]`" x-model="step.whatsapp_variables" class="form-control mt-1.5" placeholder="Awa, 5000 F" :disabled="step.type !== 'whatsapp'"><p class="mt-1.5 text-xs text-zinc-500">{{ __('Separated by commas, in the order of the variables of the template.') }}</p></div>
    <div x-show="! step.whatsapp_template">
        <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Text') }}</label>
        <textarea :name="`steps[${index}][body]`" x-model="step.body" rows="3" class="form-control mt-1.5" :disabled="step.type !== 'whatsapp'"></textarea>
        <p class="mt-1.5 text-xs text-zinc-500">{{ __('Without a template, WhatsApp only delivers within 24 hours of a message from the person.') }}</p>
    </div>
</div>

{{-- In-app message --}}
<div x-show="step.type === 'in_app'" class="space-y-3">
    @if ($inAppMessages->isEmpty())
        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('No in-app message yet.') }} <a href="{{ route('projects.in-app.create', $project) }}" class="font-medium text-brand-700 hover:underline dark:text-brand-300">{{ __('Create one') }}</a></p>
    @else
        <x-ui.select name="in-app-choice" :label="__('In-app message')" :options="['' => __('Choose an in-app message')] + $inAppMessages->mapWithKeys(fn ($m) => [$m->id => $m->name.($m->journey_only ? ' · '.__('kept for journeys') : '')])->all()"
            x-bind:name="`steps[${index}][in_app_message_id]`" x-model="step.in_app_message_id" x-bind:disabled="step.type !== 'in_app'" />
        <p class="text-xs text-zinc-500">{{ __('Shown at the next opening of the app. Turn on “Kept for journeys” on the message so that it only appears through journeys.') }}</p>
    @endif
</div>

{{-- Condition --}}
<div x-show="step.type === 'condition'" class="space-y-3">
    <input type="hidden" :name="`steps[${index}][check]`" :value="step.check" :disabled="step.type !== 'condition'">
    <input type="hidden" :name="`steps[${index}][operator]`" :value="step.operator" :disabled="step.type !== 'condition'">
    <div class="inline-flex flex-wrap gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">{!! $segment('step.check', ['tag' => __('A tag'), 'clicked' => __('A click'), 'event' => __('An event')], 'condition') !!}</div>
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Continue if') }}</span>
        <template x-if="step.check === 'tag'">
            <span class="contents">
                <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('the tag') }}</span>
                <input :name="`steps[${index}][key]`" x-model="step.key" class="form-control w-36 font-mono" placeholder="plan" aria-label="{{ __('Tag') }}">
            </span>
        </template>
        <span class="text-sm text-zinc-600 dark:text-zinc-300" x-show="step.check !== 'tag'">{{ __('the person') }}</span>
        <div class="inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">
            <button type="button" x-on:click="step.operator = 'is'" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="step.operator === 'is' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                x-text="step.check === 'tag' ? @js(__('is')) : (step.check === 'clicked' ? @js(__('clicked a message of the journey')) : @js(__('did the event')))"></button>
            <button type="button" x-on:click="step.operator = 'is_not'" class="rounded-md px-3 py-1.5 text-sm font-medium transition" :class="step.operator === 'is_not' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                x-text="step.check === 'tag' ? @js(__('is not')) : (step.check === 'clicked' ? @js(__('did not click')) : @js(__('did not do the event')))"></button>
        </div>
        <template x-if="step.check === 'tag'">
            <input :name="`steps[${index}][value]`" x-model="step.value" class="form-control w-36 font-mono" placeholder="{{ __('any value') }}" aria-label="{{ __('Value') }}">
        </template>
        <template x-if="step.check === 'event'">
            <span class="contents">
                <input :name="`steps[${index}][event]`" x-model="step.event" class="form-control w-36 font-mono" placeholder="purchase" aria-label="{{ __('Event name') }}">
                <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('in the last') }}</span>
                <input type="number" min="1" max="8760" :name="`steps[${index}][hours]`" x-model="step.hours" class="form-control w-20" placeholder="—" aria-label="{{ __('Hours') }}">
                <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('hours (empty: since entering)') }}</span>
            </span>
        </template>
    </div>
    <div class="flex flex-wrap items-center gap-3 border-t border-dashed border-zinc-200 pt-3 dark:border-zinc-800">
        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Otherwise') }}</span>
        <input type="hidden" :name="`steps[${index}][otherwise]`" :value="step.otherwise" :disabled="step.type !== 'condition'">
        <div class="inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">{!! $segment('step.otherwise', ['exit' => __('leave the journey'), 'goto' => __('go to step')], 'condition') !!}</div>
        <select x-show="step.otherwise === 'goto'" x-model.number="step.goto_uid" class="form-control w-auto min-w-48" aria-label="{{ __('Step') }}">
            <option value="">{{ __('Choose a step') }}</option>
            <template x-for="option in stepOptions(step.uid)" :key="option.uid"><option :value="option.uid" x-text="option.label" :selected="option.uid === step.goto_uid"></option></template>
        </select>
        <input type="hidden" :name="`steps[${index}][goto]`" :value="number(step.goto_uid)" :disabled="step.type !== 'condition' || step.otherwise !== 'goto'">
    </div>
</div>

<p x-show="step.type === 'exit'" class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('The person completes the journey here: useful to end branch A before the steps of branch B.') }}</p>

{{-- A/B split --}}
<div x-show="step.type === 'split'" class="space-y-4">
    <div>
        <div class="flex items-center justify-between text-sm font-medium">
            <span class="text-emerald-700 dark:text-emerald-300">A · <span x-text="step.percent"></span> %</span>
            <span class="text-zinc-600 dark:text-zinc-300">B · <span x-text="100 - step.percent"></span> %</span>
        </div>
        <input type="range" min="1" max="99" :name="`steps[${index}][percent]`" x-model.number="step.percent" class="mt-2 w-full accent-brand-600" :disabled="step.type !== 'split'" aria-label="{{ __('Share of branch A') }}">
        <p class="mt-1 text-xs text-zinc-500">{{ __('Each person always gets the same branch. Compare the clicks of the messages of each branch on the page of the journey.') }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Branch A continues with the next step. Branch B') }}</span>
        <input type="hidden" :name="`steps[${index}][b_target]`" :value="step.b_target" :disabled="step.type !== 'split'">
        <div class="inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">{!! $segment('step.b_target', ['goto' => __('goes to step'), 'exit' => __('leaves the journey')], 'split') !!}</div>
        <select x-show="step.b_target === 'goto'" x-model.number="step.b_goto_uid" class="form-control w-auto min-w-48" aria-label="{{ __('Step') }}">
            <option value="">{{ __('Choose a step') }}</option>
            <template x-for="option in stepOptions(step.uid)" :key="option.uid"><option :value="option.uid" x-text="option.label" :selected="option.uid === step.b_goto_uid"></option></template>
        </select>
        <input type="hidden" :name="`steps[${index}][b_goto]`" :value="number(step.b_goto_uid)" :disabled="step.type !== 'split' || step.b_target !== 'goto'">
    </div>
</div>
