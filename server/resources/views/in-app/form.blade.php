@php
    $editing = $message->exists;
    $buttons = old('buttons', $message->buttons ?? []);
    $config = [
        'layout' => old('layout', $message->layout ?? 'modal'),
        'title' => old('title', $message->title ?? ''),
        'body' => old('body', $message->body ?? ''),
        'image' => old('image_url', $message->image_url ?? ''),
        'trigger' => old('trigger_type', $message->trigger['type'] ?? 'app_open'),
        'frequency' => old('frequency', $message->frequency ?? 'once'),
        'ab' => (bool) old('ab_enabled', filled($message->variant_b['body'] ?? null)),
        'titleB' => old('variant_b_title', $message->variant_b['title'] ?? ''),
        'bodyB' => old('variant_b_body', $message->variant_b['body'] ?? ''),
        'showB' => false,
        'buttons' => collect(range(0, 1))->map(fn ($i) => [
            'text' => $buttons[$i]['text'] ?? '',
            'action' => $buttons[$i]['action'] ?? 'dismiss',
            'url' => $buttons[$i]['url'] ?? '',
        ])->all(),
    ];
    $segmentOptions = ['' => __('Everybody')] + collect(App\Models\Segment::BUILT_IN)->reject(fn ($s) => in_array($s, ['All', 'Subscribed Users'], true))->mapWithKeys(fn ($s) => [$s => __($s)])->all() + $segments->mapWithKeys(fn ($s) => [$s => $s])->all();
    $choice = 'rounded-md px-3 py-1.5 text-sm font-medium transition';
@endphp
<x-layouts.app :title="($editing ? $message->name : __('New in-app message')).' · '.$project->name">
    <x-ui.page-header :title="$editing ? $message->name : __('New in-app message')" :description="__('Shown inside the open app by the Carillon SDK.')">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.in-app.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('In-app messages') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span>{{ $editing ? __('Edit') : __('New') }}</span>
        </x-slot:breadcrumb>
        @if ($editing)
            <form method="POST" action="{{ route('projects.in-app.destroy', [$project, $message]) }}" data-confirm-title="{{ __('Delete this in-app message?') }}" data-confirm="{{ __('Apps stop showing it.') }}" data-confirm-action="{{ __('Delete') }}">
                @csrf
                @method('DELETE')
                <x-ui.button variant="ghost-danger" icon="trash">{{ __('Delete') }}</x-ui.button>
            </form>
        @endif
    </x-ui.page-header>

    <form method="POST" action="{{ $editing ? route('projects.in-app.update', [$project, $message]) : route('projects.in-app.store', $project) }}"
        x-data="@js($config)" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="min-w-0 space-y-6">
            @if (! $editing && $recipes)
                <div class="grid gap-2 sm:grid-cols-4">
                    @foreach (['announce' => 'sparkles', 'offer' => 'zap', 'update' => 'refresh', 'review' => 'message'] as $recipe => $icon)
                        <a href="{{ route('projects.in-app.create', [$project, 'recipe' => $recipe]) }}" @class(['flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition', 'border-brand-500 bg-brand-50/50 text-zinc-900 dark:bg-brand-500/10 dark:text-white' => request('recipe') === $recipe, 'border-zinc-200 text-zinc-600 hover:border-zinc-300 dark:border-zinc-800 dark:text-zinc-300' => request('recipe') !== $recipe])>
                            <x-ui.icon :name="$icon" class="size-4 text-brand-600" />{{ $recipes[$recipe]['name'] }}
                        </a>
                    @endforeach
                </div>
            @endif
            <x-ui.card :title="__('Message')" icon="message">
                <div class="space-y-5">
                    <x-ui.input name="name" :label="__('Name')" :value="old('name', $message->name)" required maxlength="120" :hint="__('Only seen in Carillon.')" />
                    <div>
                        <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Layout') }}</p>
                        <input type="hidden" name="layout" :value="layout">
                        <div class="grid grid-cols-3 gap-3">
                            @foreach (['banner' => __('Banner'), 'modal' => __('Pop-up'), 'fullscreen' => __('Full screen')] as $value => $label)
                                <button type="button" x-on:click="layout = '{{ $value }}'" class="rounded-xl border p-3 text-sm font-medium transition"
                                    :class="layout === '{{ $value }}' ? 'border-brand-500 bg-brand-50/50 text-zinc-900 ring-4 ring-brand-500/10 dark:bg-brand-500/10 dark:text-white' : 'border-zinc-200 text-zinc-600 hover:border-zinc-300 dark:border-zinc-800 dark:text-zinc-300'">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" for="title">{{ __('Title') }} <span class="font-normal text-zinc-400">{{ __('Optional') }}</span></label>
                        <input id="title" name="title" x-model="title" maxlength="120" class="form-control mt-1.5">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" for="body">{{ __('Text') }}</label>
                        <textarea id="body" name="body" x-model="body" rows="3" maxlength="600" required class="form-control mt-1.5"></textarea>
                        @error('body')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="layout !== 'banner'">
                        <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" for="image_url">{{ __('Image') }} <span class="font-normal text-zinc-400">{{ __('Optional') }}</span></label>
                        <input id="image_url" type="url" name="image_url" x-model="image" placeholder="https://…" class="form-control mt-1.5">
                        @error('image_url')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="layout !== 'banner'" class="space-y-3">
                        <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Buttons') }}</p>
                        <template x-for="(button, index) in buttons" :key="index">
                            <div class="flex flex-wrap items-center gap-2">
                                <input :name="`buttons[${index}][text]`" x-model="button.text" maxlength="40" class="form-control w-40" :placeholder="index === 0 ? @js(__('e.g. Discover')) : @js(__('Second button (optional)'))">
                                <input type="hidden" :name="`buttons[${index}][action]`" :value="button.action">
                                <div class="inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">
                                    <button type="button" x-on:click="button.action = 'dismiss'" class="{{ $choice }}" :class="button.action === 'dismiss' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500'">{{ __('Close') }}</button>
                                    <button type="button" x-on:click="button.action = 'url'" class="{{ $choice }}" :class="button.action === 'url' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500'">{{ __('Open a link') }}</button>
                                </div>
                                <input x-show="button.action === 'url'" :name="`buttons[${index}][url]`" x-model="button.url" class="form-control min-w-0 flex-1" placeholder="https:// {{ __('or') }} myapp://offers">
                            </div>
                        </template>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('When and to whom')" icon="zap">
                <div class="space-y-5">
                    <div>
                        <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Show it') }}</p>
                        <input type="hidden" name="trigger_type" :value="trigger">
                        <div class="inline-flex gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">
                            <button type="button" x-on:click="trigger = 'app_open'" class="{{ $choice }}" :class="trigger === 'app_open' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500'">{{ __('When the app opens') }}</button>
                            <button type="button" x-on:click="trigger = 'event'" class="{{ $choice }}" :class="trigger === 'event' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500'">{{ __('After an event') }}</button>
                        </div>
                        <div x-show="trigger === 'event'" x-cloak class="mt-3">
                            <input name="trigger_event" value="{{ old('trigger_event', $message->trigger['event'] ?? '') }}" class="form-control font-mono sm:w-72" placeholder="purchase" aria-label="{{ __('Event name') }}" :disabled="trigger !== 'event'">
                            <p class="mt-1.5 text-xs text-zinc-500">{{ __('Reported by the app with Carillon.user.trackEvent().') }}</p>
                        </div>
                    </div>
                    <x-ui.select name="segment" :label="__('To')" :value="old('segment', $message->segment)" :options="$segmentOptions" />
                    <input type="hidden" name="journey_only" value="0">
                    <x-ui.toggle name="journey_only" :checked="(bool) old('journey_only', $message->journey_only)" :label="__('Kept for journeys')" :description="__('Shown only to the people a journey step gives it to, at their next opening of the app.')" />
                    <div>
                        <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('How often') }}</p>
                        <input type="hidden" name="frequency" :value="frequency">
                        <div class="inline-flex flex-wrap gap-0.5 rounded-lg bg-zinc-200/60 p-1 dark:bg-white/[0.06]">
                            @foreach (['once' => __('Once'), 'every_session' => __('Every session'), 'every_days' => __('Every few days')] as $value => $label)
                                <button type="button" x-on:click="frequency = '{{ $value }}'" class="{{ $choice }}" :class="frequency === '{{ $value }}' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500'">{{ $label }}</button>
                            @endforeach
                        </div>
                        <div x-show="frequency === 'every_days'" x-cloak class="mt-3 flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ __('At most every') }}
                            <input type="number" min="1" max="365" name="frequency_days" value="{{ old('frequency_days', $message->frequency_days ?? 3) }}" class="form-control w-20" :disabled="frequency !== 'every_days'">
                            {{ __('days') }}
                        </div>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="trigger_delay" type="number" min="0" max="600" :label="__('Delay (seconds)')" :value="old('trigger_delay', $message->trigger['delay_seconds'] ?? '')" placeholder="0" :hint="__('Time in the app before it shows, so it does not hide the first screen.')" optional />
                        <x-ui.input name="min_sessions" type="number" min="1" max="10000" :label="__('From the session')" :value="old('min_sessions', $message->trigger['min_sessions'] ?? '')" placeholder="1" :hint="__('E.g. 5: only people who opened the app at least 5 times.')" optional />
                    </div>
                    <x-ui.input name="ends_at" type="datetime-local" :label="__('Stop showing it on')" :value="old('ends_at', $message->ends_at?->format('Y-m-d\TH:i'))" optional />
                </div>
            </x-ui.card>

            <x-ui.card :title="__('A/B test')" :description="__('A second text for half of the people: the list shows which one gets more clicks.')" icon="activity">
                <div class="space-y-4">
                    <x-ui.toggle name="ab_enabled" x-model="ab" :checked="(bool) old('ab_enabled', filled($message->variant_b['body'] ?? null))" :label="__('Test a version B')" />
                    <div x-show="ab" x-cloak class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" for="variant_b_title">{{ __('Title B') }} <span class="font-normal text-zinc-400">{{ __('Optional') }}</span></label>
                            <input id="variant_b_title" name="variant_b_title" x-model="titleB" maxlength="120" class="form-control mt-1.5" x-on:focus="showB = true">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-zinc-800 dark:text-zinc-200" for="variant_b_body">{{ __('Text B') }}</label>
                            <textarea id="variant_b_body" name="variant_b_body" x-model="bodyB" rows="3" maxlength="600" class="form-control mt-1.5" x-on:focus="showB = true"></textarea>
                            @error('variant_b_body')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

        {{-- Preview --}}
        <aside class="space-y-4 lg:sticky lg:top-8">
            <div class="card p-5">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Preview') }}</p>
                    <div x-show="ab" x-cloak class="inline-flex gap-0.5 rounded-md bg-zinc-100 p-0.5 text-xs dark:bg-white/5">
                        <button type="button" x-on:click="showB = false" class="rounded px-2 py-0.5 font-medium" :class="!showB ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500'">A</button>
                        <button type="button" x-on:click="showB = true" class="rounded px-2 py-0.5 font-medium" :class="showB ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white' : 'text-zinc-500'">B</button>
                    </div>
                </div>
                <div class="relative mx-auto aspect-[9/17] w-56 overflow-hidden rounded-[28px] border-[6px] border-zinc-900 bg-gradient-to-b from-zinc-100 to-zinc-200 dark:from-zinc-800 dark:to-zinc-900">
                    <div class="absolute inset-x-6 top-10 space-y-2 opacity-60">
                        <div class="h-3 w-24 rounded bg-zinc-300 dark:bg-zinc-700"></div>
                        <div class="h-16 rounded-lg bg-zinc-300/70 dark:bg-zinc-700/70"></div>
                        <div class="h-16 rounded-lg bg-zinc-300/70 dark:bg-zinc-700/70"></div>
                    </div>
                    {{-- Banner --}}
                    <div x-show="layout === 'banner'" class="absolute inset-x-2 top-3 rounded-xl bg-white p-3 text-left shadow-lg dark:bg-zinc-800">
                        <p class="text-[11px] font-semibold text-zinc-900 dark:text-white" x-text="ab && showB ? titleB : title" x-show="ab && showB ? titleB : title"></p>
                        <p class="text-[11px] leading-snug text-zinc-600 dark:text-zinc-300" x-text="(ab && showB ? bodyB : body) || @js(__('Your text'))"></p>
                    </div>
                    {{-- Pop-up and full screen --}}
                    <div x-show="layout !== 'banner'" class="absolute inset-0 flex items-center justify-center bg-zinc-950/40 p-3" :class="layout === 'fullscreen' && '!p-0'">
                        <div class="w-full overflow-hidden bg-white text-center shadow-xl dark:bg-zinc-800" :class="layout === 'fullscreen' ? 'flex h-full flex-col justify-center' : 'rounded-xl'">
                            <img x-show="image" :src="image" alt="" class="h-20 w-full object-cover" :class="layout === 'fullscreen' && '!h-32'">
                            <div class="p-3">
                                <p class="text-xs font-semibold text-zinc-900 dark:text-white" x-text="ab && showB ? titleB : title" x-show="ab && showB ? titleB : title"></p>
                                <p class="mt-1 text-[11px] leading-snug text-zinc-600 dark:text-zinc-300" x-text="(ab && showB ? bodyB : body) || @js(__('Your text'))"></p>
                                <div class="mt-3 space-y-1.5">
                                    <template x-for="(button, index) in buttons.filter(b => b.text)" :key="index">
                                        <div class="rounded-md py-1.5 text-[11px] font-semibold" :class="index === 0 ? 'bg-brand-600 text-white' : 'bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-200'" x-text="button.text"></div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card p-5">
                <x-ui.button class="w-full" icon="check">{{ $editing ? __('Save') : __('Create the in-app message') }}</x-ui.button>
                <p class="mt-3 text-xs text-zinc-500">{{ $editing ? __('Apps get the change at their next opening.') : __('It is saved as a draft: activate it from the list.') }}</p>
            </div>
        </aside>
    </form>
</x-layouts.app>
