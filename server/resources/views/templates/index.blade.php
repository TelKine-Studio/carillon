@php
    $locale = app()->getLocale();
@endphp
<x-layouts.app :title="__('Templates').' · '.$project->name">
    <x-ui.page-header :title="__('Templates')" :description="__('Messages you send often: start from one in the composer, or pass its id as template_id in the API.')">
        @can('send', $project)
            <x-ui.button :href="route('projects.templates.create', $project)" icon="plus">{{ __('New template') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <x-ui.card :padding="false">
        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($templates as $template)
                @php
                    $title = \App\Models\Message::localized($template->headings, $locale);
                    $body = \App\Models\Message::localized($template->contents, $locale);
                @endphp
                <li class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4 sm:px-6">
                    <div class="min-w-0 flex-1 basis-64">
                        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $template->name }}</p>
                        <p class="mt-0.5 truncate text-sm text-zinc-500 dark:text-zinc-400">
                            @if ($title)<span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $title }}</span> · @endif{{ $body }}
                        </p>
                        <p class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-500">
                            <span class="inline-flex items-center gap-1 font-mono" x-data="{ copied: false }">
                                {{ $template->id }}
                                <button type="button" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200" x-on:click="navigator.clipboard.writeText(@js($template->id)); copied = true; setTimeout(() => copied = false, 1500)" title="{{ __('Copy') }}">
                                    <x-ui.icon name="copy" class="size-3.5" x-show="!copied" /><x-ui.icon name="check" class="size-3.5 text-emerald-600" x-show="copied" x-cloak />
                                </button>
                            </span>
                            <span>·</span>
                            <span>{{ trans_choice(':count language|:count languages', count($template->contents ?? [])) }}</span>
                            <span>·</span>
                            <span>{{ trans_choice('Used :count time|Used :count times', $template->messages_count) }}</span>
                            <span>·</span>
                            <span>{{ __('Updated :date', ['date' => $template->updated_at->diffForHumans()]) }}</span>
                        </p>
                    </div>
                    @can('send', $project)
                        <div class="flex items-center gap-1">
                            <x-ui.button variant="secondary" size="sm" :href="route('projects.messages.create', [$project, 'template' => $template->id])" icon="send">{{ __('Use') }}</x-ui.button>
                            <x-ui.button variant="ghost" size="sm" :href="route('projects.templates.edit', [$project, $template])" icon="settings">{{ __('Edit') }}</x-ui.button>
                            <form method="POST" action="{{ route('projects.templates.destroy', [$project, $template]) }}" data-confirm-title="{{ __('Delete this template?') }}" data-confirm="{{ __('Messages already sent are not affected; API calls using its id will fail.') }}" data-confirm-action="{{ __('Delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="ghost-danger" size="icon" icon="trash" class="size-8"><span class="sr-only">{{ __('Delete') }}</span></x-ui.button>
                            </form>
                        </div>
                    @endcan
                </li>
            @empty
                <li><x-ui.empty icon="file" :title="__('No template yet')" :description="__('For example: “Your order has shipped”, with its link and its image, ready to send.')" /></li>
            @endforelse
        </ul>
    </x-ui.card>
</x-layouts.app>
