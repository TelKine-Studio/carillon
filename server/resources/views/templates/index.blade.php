@php
    $locale = app()->getLocale();
@endphp
<x-layouts.app :title="__('Templates').' · '.$project->name">
    <x-ui.page-header :title="__('Templates')" :description="__('Messages you send often: start from one in the composer, or pass its id as template_id in the API.')">
        @can('send', $project)
            <x-ui.button :href="route('projects.templates.create', $project)" icon="plus">{{ __('New template') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

    <div class="card overflow-hidden">
        @if ($templates->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 bg-zinc-50 text-left text-xs font-medium text-zinc-500 dark:border-zinc-800 dark:bg-white/[.03] dark:text-zinc-400">
                            <th class="px-4 py-3 font-medium sm:px-5">{{ __('Name') }}</th>
                            <th class="px-3 py-3 font-medium">{{ __('Channel') }}</th>
                            <th class="px-3 py-3 text-right font-medium">{{ __('Used') }}</th>
                            <th class="px-3 py-3 font-medium whitespace-nowrap">{{ __('Updated') }}</th>
                            <th class="px-4 py-3 sm:px-5"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($templates as $template)
                            @php
                                $email = $template->option('target_channel') === 'email';
                                $title = $email ? $template->option('email_subject') : \App\Models\Message::localized($template->headings, $locale);
                                $body = $email ? ($template->option('email_preheader') ?: __('Email')) : \App\Models\Message::localized($template->contents, $locale);
                            @endphp
                            <tr class="transition hover:bg-zinc-50/80 dark:hover:bg-white/[.02]">
                                <td class="max-w-0 px-4 py-3.5 sm:px-5" style="width: 46%">
                                    <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $template->name }}</p>
                                    <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">@if ($title){{ $title }} · @endif{{ $body }}</p>
                                    <p class="mt-1 flex items-center gap-1 font-mono text-[11px] text-zinc-400" x-data="{ copied: false }">
                                        <span class="truncate">{{ $template->id }}</span>
                                        <button type="button" class="shrink-0 hover:text-zinc-700 dark:hover:text-zinc-200" x-on:click="navigator.clipboard.writeText(@js($template->id)); copied = true; setTimeout(() => copied = false, 1500)" title="{{ __('Copy') }}">
                                            <x-ui.icon name="copy" class="size-3" x-show="!copied" /><x-ui.icon name="check" class="size-3 text-emerald-600" x-show="copied" x-cloak />
                                        </button>
                                    </p>
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap text-zinc-700 dark:text-zinc-300"><span class="inline-flex items-center gap-1.5"><x-ui.icon :name="$email ? 'mail' : 'smartphone'" class="size-4 text-zinc-400" />{{ $email ? __('Email') : __('Push notification') }}</span></td>
                                <td class="px-3 py-3.5 text-right font-medium text-zinc-900 tabular-nums dark:text-white">{{ number_format($template->messages_count, 0, ',', ' ') }}</td>
                                <td class="px-3 py-3.5 whitespace-nowrap text-zinc-600 dark:text-zinc-400">{{ $template->updated_at->diffForHumans() }}</td>
                                <td class="px-4 py-3.5 sm:px-5">
                                    @can('send', $project)
                                        <div class="flex items-center justify-end gap-1">
                                            <x-ui.button variant="secondary" size="sm" :href="route('projects.messages.create', [$project, 'template' => $template->id])" icon="send">{{ __('Use') }}</x-ui.button>
                                            <x-ui.button variant="ghost" size="icon" :href="route('projects.templates.edit', [$project, $template])" icon="pencil" class="size-8" title="{{ __('Edit') }}"><span class="sr-only">{{ __('Edit') }}</span></x-ui.button>
                                            <form method="POST" action="{{ route('projects.templates.destroy', [$project, $template]) }}" data-confirm-title="{{ __('Delete this template?') }}" data-confirm="{{ __('Messages already sent are not affected; API calls using its id will fail.') }}" data-confirm-action="{{ __('Delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.button variant="ghost-danger" size="icon" icon="trash" class="size-8" title="{{ __('Delete') }}"><span class="sr-only">{{ __('Delete') }}</span></x-ui.button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-ui.empty icon="file" :title="__('No template yet')" :description="__('For example: “Your order has shipped”, with its link and its image, ready to send.')" />
        @endif
    </div>
</x-layouts.app>
