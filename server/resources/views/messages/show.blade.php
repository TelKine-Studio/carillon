@php
    $lang = app()->getLocale();
    $n = fn ($v) => number_format((int) $v, 0, ',', ' ');
    $pct = fn ($part, $total) => $total > 0 ? number_format($part / $total * 100, 1, ',', ' ').' %' : '—';
    $live = in_array($message->status, ['queued', 'sending'], true);
    $platformNames = ['ios' => ['iOS', 'apple'], 'android' => ['Android', 'android'], 'web' => ['Web', 'globe-web'], 'email' => [__('Email'), 'mail']];
    $isEmail = $message->channel === 'email';
    $preview = [
        'title' => \App\Models\Message::localized($message->headings, $lang),
        'subtitle' => \App\Models\Message::localized($message->subtitle, $lang),
        'body' => \App\Models\Message::localized($message->contents, $lang),
        'image' => $message->option('big_picture'),
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
            @if ($message->isCancelable())
                <form method="POST" action="{{ route('projects.messages.cancel', [$project, $message]) }}" data-confirm-title="{{ __('Cancel this message?') }}" data-confirm="{{ __('It will not be sent.') }}" data-confirm-action="{{ __('Cancel the message') }}">
                    @csrf
                    <x-ui.button variant="secondary" icon="x">{{ __('Cancel') }}</x-ui.button>
                </form>
            @endif
            <x-ui.button variant="secondary" :href="route('projects.messages.create', [$project, 'from' => $message->id])" icon="copy">{{ __('Duplicate') }}</x-ui.button>
        @endcan
    </x-ui.page-header>

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
                    ] as [$label, $value, $color])
                        <div class="grid grid-cols-[96px_minmax(0,1fr)_72px] items-center gap-3 text-sm">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ $label }}</span>
                            <span class="h-2 overflow-hidden rounded-sm bg-zinc-100 dark:bg-white/5"><span class="block h-full rounded-sm {{ $color }}" style="width: {{ round($value / $max * 100, 2) }}%"></span></span>
                            <span class="text-right font-medium tabular-nums text-zinc-900 dark:text-white">{{ $n($value) }}</span>
                        </div>
                    @endforeach
                </div>
                @if ($message->failed || $message->errored)
                    <p class="mt-5 flex flex-wrap gap-x-4 gap-y-1 border-t border-zinc-100 pt-4 text-sm text-zinc-500 dark:border-zinc-800">
                        <span><strong class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $n($message->failed) }}</strong> {{ __('refused (devices disabled)') }}</span>
                        <span><strong class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $n($message->errored) }}</strong> {{ __('errors') }}</span>
                    </p>
                @endif
            </x-ui.card>

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
                <x-ui.card :title="__('Errors')" :description="$isEmail ? __('Reasons given by the email service or the receiving servers.') : __('Reasons given by Apple, Google or the browsers.')" icon="alert" :padding="false">
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
