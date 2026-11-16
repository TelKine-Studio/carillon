@php
    $lang = app()->getLocale();
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $pct = fn ($part, $total) => $total > 0 ? number_format($part / $total * 100, 1, ',', ' ').' %' : '—';
    $live = in_array($message->status, ['queued', 'sending'], true);
    $platformNames = ['ios' => ['iOS', 'apple'], 'android' => ['Android', 'android'], 'huawei' => ['Huawei', 'android'], 'amazon' => ['Amazon', 'android'], 'web' => ['Web', 'globe-web'], 'email' => [__('Email'), 'mail'], 'sms' => ['SMS', 'message'], 'whatsapp' => ['WhatsApp', 'chat']];
    $isEmail = $message->channel === 'email';
    $preview = [
        'title' => \App\Models\Message::localized($message->headings, $lang),
        'subtitle' => \App\Models\Message::localized($message->subtitle, $lang),
        'body' => \App\Models\Message::localized($message->contents, $lang),
        'image' => $message->option('big_picture'),
        'icon' => $message->option('large_icon') ?? $message->option('chrome_web_icon'),
        'buttons' => collect($message->option('buttons', []))->pluck('text')->all(),
    ];
@endphp
<x-layouts.app :title="$message->label()">
    @if ($live)
        {{-- Refreshes while the message is being sent. --}}
        <meta http-equiv="refresh" content="4">
    @endif

    <x-ui.page-header :title="$message->label()">
        <x-slot:breadcrumb>
            <a href="{{ route('projects.messages.index', $project) }}" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Messages') }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($message->id, 8, '') }}</span>
        </x-slot:breadcrumb>
        @can('send', $project)
            @if ($message->status === 'draft')
                <form method="POST" action="{{ route('projects.messages.destroy-draft', [$project, $message]) }}" data-confirm-title="{{ __('Delete this draft?') }}" data-confirm="{{ __('It cannot be recovered.') }}" data-confirm-action="{{ __('Delete') }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="ghost-danger" icon="trash">{{ __('Delete') }}</x-ui.button>
                </form>
                <x-ui.button :href="route('projects.messages.create', [$project, 'from' => $message->id])" icon="pencil">{{ __('Edit the draft') }}</x-ui.button>
            @endif
            @if ($message->isCancelable())
                <form method="POST" action="{{ route('projects.messages.cancel', [$project, $message]) }}" data-confirm-title="{{ __('Cancel this message?') }}" data-confirm="{{ __('It will not be sent.') }}" data-confirm-action="{{ __('Cancel the message') }}">
                    @csrf
                    <x-ui.button variant="secondary" icon="x">{{ __('Cancel') }}</x-ui.button>
                </form>
            @endif
            @if ($message->status !== 'draft')
                <x-ui.button variant="secondary" :href="route('projects.messages.create', [$project, 'from' => $message->id])" icon="copy">{{ __('Duplicate') }}</x-ui.button>
            @endif
        @endcan
        @if ($message->ab_test_id)
            <x-ui.button variant="secondary" :href="route('projects.ab-tests.show', [$project, $message->ab_test_id])" icon="activity">{{ __('A/B test') }}</x-ui.button>
        @endif
        @if ($message->recipients > 0 && $message->status !== 'draft')
            <x-ui.button variant="secondary" :href="route('projects.messages.export', [$project, $message])" icon="file" title="{{ __('Every device: delivered, received, clicked (CSV).') }}">{{ __('Export') }}</x-ui.button>
        @endif
    </x-ui.page-header>

    @if ($message->labels || auth()->user()->can('send', $project))
        <div class="-mt-2 mb-4 flex flex-wrap items-center gap-1.5">
            @foreach ($message->labels ?? [] as $item)
                <a href="{{ route('projects.messages.index', [$project, 'label' => $item]) }}"><x-ui.badge>{{ $item }}</x-ui.badge></a>
            @endforeach
            @can('send', $project)
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'labels')" class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-white/5 dark:hover:text-white">
                    <x-ui.icon name="tag" class="size-3.5" />{{ $message->labels ? __('Edit labels') : __('Add labels') }}
                </button>
                <x-ui.modal name="labels" :title="__('Labels')" :description="__('To sort and filter the messages. Separated by commas.')">
                    <form method="POST" action="{{ route('projects.messages.labels', [$project, $message]) }}" class="space-y-5">
                        @csrf
                        @method('PUT')
                        <x-ui.input name="labels" :label="__('Labels')" :value="implode(', ', $message->labels ?? [])" :placeholder="__('e.g. promo, newsletter')" maxlength="300" />
                        <div class="flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'labels')">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button icon="check">{{ __('Save') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.modal>
            @endcan
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-zinc-500 dark:text-zinc-400">
        <x-messages.status :status="$message->status" />
        <span>{{ $message->audienceLabel() }}</span>
        <span>·</span>
        <span>
            @if ($message->status === 'scheduled')
                {{ __('Scheduled for :date', ['date' => $message->send_after->isoFormat('LLL')]) }}
            @elseif ($message->status === 'sending' && $message->option('delayed_option') === 'timezone')
                {{ __('Delivered at :time in the time zone of each device', ['time' => $message->option('delivery_time_of_day')]) }}
            @elseif ($message->completed_at)
                {{ __('Sent :date', ['date' => $message->completed_at->isoFormat('LLL')]) }}
            @else
                {{ __('Created :date', ['date' => $message->created_at->isoFormat('LLL')]) }}
            @endif
        </span>
        <span>·</span>
        <span>{{ $message->author?->name ?? ($message->api_key_id ? __('Sent with the API') : '—') }}</span>
        @if ($message->template)
            <span>·</span>
            <a href="{{ route('projects.templates.edit', [$project, $message->template]) }}" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white"><x-ui.icon name="file" class="size-3.5" />{{ $message->template->name }}</a>
        @endif
    </div>

    @if ($message->error)
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />{{ $message->error }}
        </div>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-ui.stat :label="__('Recipients')" :value="$n($message->recipients)" icon="users" :hint="$live ? __(':count remaining', ['count' => $n($message->remaining())]) : null" />
        <x-ui.stat :label="__('Delivered')" :value="$n($message->successful)" icon="send" :hint="__(':rate of recipients', ['rate' => $pct($message->successful, $message->recipients)])" />
        <x-ui.stat :label="$isEmail ? __('Opened') : __('Received')" :value="$n($message->received)" :icon="$isEmail ? 'mail' : 'smartphone'" :hint="$isEmail ? __(':rate open rate', ['rate' => $pct($message->received, $message->successful)]) : __(':rate confirmed by the SDK', ['rate' => $pct($message->received, $message->successful)])" />
        <x-ui.stat :label="__('Clicked')" :value="$n($message->clicked)" icon="external" :hint="__(':rate click rate', ['rate' => $pct($message->clicked, $message->successful)])" />
    </div>

    @if ($legacy)
        <div class="mt-6 flex items-start gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200">
            <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0" />
            <p>{{ trans_choice(':count device of this message still runs the OneSignal SDK: it displays the notification, but its receptions and clicks are not reported to Carillon. They will be once the app is updated with the Carillon SDK.|:count devices of this message still run the OneSignal SDK: they display the notification, but their receptions and clicks are not reported to Carillon. They will be once the app is updated with the Carillon SDK.', $legacy, ['count' => number_format($legacy, 0, ',', ' ')]) }}</p>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        <div class="min-w-0 space-y-6">
            {{-- Funnel --}}
            <x-ui.card :title="__('Delivery')" icon="layers">
                @php $max = max(1, $message->recipients); @endphp
                <div class="space-y-3">
                    @foreach ([
                        [__('Recipients'), $message->recipients, 'bg-zinc-300 dark:bg-zinc-600'],
                        [__('Delivered'), $message->successful, 'bg-brand-500'],
                        [$isEmail ? __('Opened') : __('Received'), $message->received, 'bg-brand-400'],
                        [__('Clicked'), $message->clicked, 'bg-emerald-500'],
                        [__('Converted'), $converted, 'bg-emerald-700'],
                    ] as $row => [$label, $value, $color])
                        @continue($row === 4 && ! $converted)
                        <div class="grid grid-cols-[96px_minmax(0,1fr)_120px] items-center gap-3 text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $label }}</span>
                            <span class="h-2 overflow-hidden rounded-sm bg-zinc-100 dark:bg-white/5"><span class="block h-full rounded-sm {{ $color }}" style="width: {{ round($value / $max * 100, 2) }}%"></span></span>
                            <span class="text-right font-medium tabular-nums text-zinc-900 dark:text-white">{{ $n($value) }}@if ($row > 1 && $message->successful)<span class="ml-1.5 text-xs font-normal text-zinc-400">{{ number_format($value / $message->successful * 100, 1, ',', ' ') }} %</span>@endif</span>
                        </div>
                    @endforeach
                </div>
                @if ($message->capped)
                    <p class="mt-5 flex items-start gap-2 border-t border-zinc-100 pt-4 text-sm text-zinc-500 dark:border-zinc-800">
                        <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0" />
                        <span>{{ trans_choice(':count device skipped by the daily cap of the project.|:count devices skipped by the daily cap of the project.', $message->capped, ['count' => $n($message->capped)]) }}</span>
                    </p>
                @endif
                @if ($message->failed || $message->errored)
                    <p class="mt-5 flex flex-wrap gap-x-4 gap-y-1 border-t border-zinc-100 pt-4 text-sm text-zinc-500 dark:border-zinc-800">
                        <span><strong class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $n($message->failed) }}</strong> {{ __('refused (devices disabled)') }}</span>
                        <span><strong class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $n($message->errored) }}</strong> {{ __('errors') }}</span>
                    </p>
                @endif
            </x-ui.card>

            @if ($clicks)
                <x-ui.card :title="__('Clicks over time')" :description="__('Per hour, during the first two days.')" icon="external">
                    <x-ui.bar-chart :points="$clicks" :label="__('Clicks over time')" tick-format="ddd HH[h]" tooltip="ddd D MMM, HH[h]" height="h-32" />
                </x-ui.card>
            @endif

            @if ($conversions->isNotEmpty())
                <x-ui.card :title="__('Conversions')" :description="__('Events of the people in the :hours hours after this message: direct after a click, influenced after a reception.', ['hours' => \App\Audience\Conversions::WINDOW])" icon="sparkles" :padding="false">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-zinc-500">
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <th class="px-5 py-2.5 text-left font-medium sm:px-6">{{ __('Event') }}</th>
                                <th class="px-3 py-2.5 text-right font-medium">{{ __('Direct') }}</th>
                                <th class="px-3 py-2.5 text-right font-medium">{{ __('Influenced') }}</th>
                                <th class="px-5 py-2.5 text-right font-medium sm:px-6">{{ __('Value') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($conversions as $name => $rows)
                                <tr>
                                    <td class="px-5 py-3 font-mono text-xs text-zinc-900 sm:px-6 dark:text-zinc-100">{{ $name }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums text-zinc-900 dark:text-zinc-100">{{ $n($rows->firstWhere('attribution', 'direct')?->total ?? 0) }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums text-zinc-900 dark:text-zinc-100">{{ $n($rows->firstWhere('attribution', 'influenced')?->total ?? 0) }}</td>
                                    <td class="px-5 py-3 text-right font-semibold tabular-nums text-zinc-900 sm:px-6 dark:text-white">{{ $rows->sum('amount') ? number_format((float) $rows->sum('amount'), 0, ',', ' ') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.card>
            @endif

            {{-- Per platform --}}
            @if ($message->platformStats->isNotEmpty())
                <x-ui.card :title="__('By platform')" icon="smartphone" :padding="false">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead>
                                <tr class="border-b border-zinc-100 text-left text-xs text-zinc-500 dark:border-zinc-800">
                                    <th class="px-5 py-2.5 font-medium sm:px-6">{{ __('Platform') }}</th>
                                    @foreach ([__('Recipients'), __('Delivered'), __('Refused'), __('Errors'), __('Clicked')] as $heading)
                                        <th class="px-3 py-2.5 text-right font-medium">{{ $heading }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($message->platformStats as $stat)
                                    <tr>
                                        <td class="px-5 py-3 sm:px-6"><span class="inline-flex items-center gap-2 font-medium text-zinc-900 dark:text-white"><x-ui.icon :name="$platformNames[$stat->platform][1] ?? 'smartphone'" class="size-4 text-zinc-400" />{{ $platformNames[$stat->platform][0] ?? $stat->platform }}</span></td>
                                        @foreach (['recipients', 'successful', 'failed', 'errored', 'clicked'] as $column)
                                            <td class="px-3 py-3 text-right tabular-nums text-zinc-700 dark:text-zinc-300">{{ $n($stat->{$column}) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            @endif

            @if ($errors_summary->isNotEmpty())
                <x-ui.card :title="__('Errors')" :description="$isEmail ? __('Reasons given by the email service or the receiving servers.') : ($message->channel === 'sms' ? __('Reasons given by the SMS provider or the carriers.') : __('Reasons given by Apple, Google or the browsers.'))" icon="alert" :padding="false">
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($errors_summary as $row)
                            <li class="flex items-center justify-between gap-4 px-5 py-3 text-sm sm:px-6">
                                <span class="min-w-0 truncate font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $row->error }}</span>
                                <span class="shrink-0 font-medium tabular-nums text-zinc-900 dark:text-white">{{ $n($row->total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            {{-- Content --}}
            @if ($isEmail)
                @php
                    $subject = $message->option('email_subject');
                    $emailDetails = array_filter([
                        __('Subject') => is_array($subject) ? \App\Models\Message::localized($subject, $lang) : $subject,
                        __('Preview text') => is_string($message->option('email_preheader')) ? $message->option('email_preheader') : null,
                        __('Sender name') => $message->option('email_from_name'),
                    ], fn ($v) => filled($v));
                @endphp
                <x-ui.card :title="__('Content')" icon="mail">
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        @foreach ($emailDetails as $label => $value)
                            <div class="min-w-0"><dt class="text-xs text-zinc-500">{{ $label }}</dt><dd class="truncate text-zinc-800 dark:text-zinc-200">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </x-ui.card>
            @else
            <x-ui.card :title="__('Content')" icon="message">
                <dl class="space-y-4 text-sm">
                    @foreach ($message->contents as $language => $body)
                        <div class="rounded-lg border border-zinc-100 p-4 dark:border-zinc-800">
                            <p class="mb-2 font-mono text-xs text-zinc-400 uppercase">{{ $language }}</p>
                            @if ($title = $message->headings[$language] ?? null)<p class="font-semibold text-zinc-900 dark:text-white">{{ $title }}</p>@endif
                            @if ($subtitle = $message->subtitle[$language] ?? null)<p class="font-medium text-zinc-700 dark:text-zinc-300">{{ $subtitle }}</p>@endif
                            <p class="whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $body }}</p>
                        </div>
                    @endforeach
                    @php
                        $details = array_filter([
                            __('Link') => $message->option('url'),
                            __('Image') => $message->option('big_picture'),
                            __('Collapse id') => $message->option('collapse_id'),
                            __('Priority') => $message->option('priority') ? ((int) $message->option('priority') >= 10 ? __('High') : __('Normal')) : null,
                            __('Time to live (seconds)') => $message->option('ttl'),
                        ], fn ($v) => $v !== null && $v !== '');
                    @endphp
                    @if ($details || $message->option('data'))
                        <div class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            @foreach ($details as $label => $value)
                                <div class="min-w-0"><dt class="text-xs text-zinc-500">{{ $label }}</dt><dd class="truncate text-zinc-800 dark:text-zinc-200">{{ $value }}</dd></div>
                            @endforeach
                        </div>
                    @endif
                    @if ($message->option('data'))
                        <x-ui.code :label="__('Additional data')">{{ json_encode($message->option('data'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</x-ui.code>
                    @endif
                </dl>
            </x-ui.card>
            @endif
        </div>

        <aside class="space-y-4 lg:sticky lg:top-6">
            @if ($isEmail)
                @php
                    $body = $message->option('email_body');
                    $body = is_array($body) ? \App\Models\Message::localized($body, $lang) : (string) $body;
                    $sample = \App\Email\EmailBuilder::render($body, ['email' => 'ada@example.com', 'external_id' => 'user-42', 'tags' => ['first_name' => 'Ada'], 'unsubscribe_url' => '#'], true);
                @endphp
                <x-ui.card :title="__('Preview')" icon="mail" :padding="false">
                    <iframe title="{{ __('Preview') }}" class="mt-1 block h-[28rem] w-full rounded-b-2xl border-t border-zinc-100 bg-white dark:border-zinc-800" sandbox="" srcdoc="{{ $sample }}"></iframe>
                </x-ui.card>
            @elseif ($message->channel === 'whatsapp')
                <x-ui.card :title="__('Preview')" icon="chat">
                    <div class="rounded-2xl bg-[#efe7dd] p-4 dark:bg-[#0b141a]">
                        <p class="ml-auto max-w-[85%] rounded-xl rounded-tr-sm bg-[#d9fdd3] px-3 py-2 text-sm break-words whitespace-pre-line text-zinc-900 shadow-sm dark:bg-[#005c4b] dark:text-zinc-100">{{ \App\Models\Message::localized($message->contents, $lang) ?? $message->option('whatsapp_template') }}</p>
                    </div>
                    @if ($message->option('whatsapp_template'))
                        <p class="mt-3 text-xs text-zinc-500">{{ __('Template :name (:language)', ['name' => $message->option('whatsapp_template'), 'language' => $message->option('whatsapp_language')]) }}</p>
                    @endif
                </x-ui.card>
            @elseif ($message->channel === 'sms')
                <x-ui.card :title="__('Preview')" icon="message">
                    <div class="rounded-2xl bg-zinc-100 p-4 dark:bg-white/5">
                        <p class="max-w-[85%] rounded-2xl rounded-bl-md bg-white px-3.5 py-2.5 text-sm whitespace-pre-line text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-zinc-100">{{ \App\Email\EmailBuilder::render((string) \App\Models\Message::localized($message->contents, $lang), ['phone_number' => '+2250700000000', 'external_id' => 'user-42', 'tags' => ['first_name' => 'Ada']], false) }}</p>
                    </div>
                </x-ui.card>
            @else
            <x-ui.card :title="__('Preview')" icon="smartphone">
                <div x-data="{ preview: @js($preview) }">
                    <x-messages.preview :project="$project" />
                </div>
            </x-ui.card>
            @endif
        </aside>
    </div>
</x-layouts.app>
