@php
    $labels = [
        'message.sent' => [__('Message sent'), __('A message has been sent to all its recipients, with its totals.')],
        'notification.received' => [__('Notification received'), __('Shown on a device (reported by the SDK).')],
        'notification.clicked' => [__('Notification clicked'), __('Opened by a person, with the button pressed.')],
        'subscription.created' => [__('New device'), __('A device registered for notifications.')],
        'subscription.unsubscribed' => [__('Device unsubscribed'), __('Notifications turned off, or the app uninstalled.')],
    ];
    $open = session('open_webhook');
@endphp
<x-projects.settings :project="$project" :section="$section">
    <x-ui.card :title="__('Webhooks')" :description="__('Carillon sends a POST request to your server for the events you choose, signed with a secret.')" icon="link" :padding="false">
        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($webhooks as $webhook)
                <li x-data="{ open: @js($open === $webhook->id) }">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 sm:px-6">
                        <button type="button" class="flex min-w-0 flex-1 basis-64 items-start gap-3 text-left" x-on:click="open = !open" :aria-expanded="open">
                            <x-ui.icon name="chevron-right" class="mt-0.5 size-4 shrink-0 text-zinc-400 transition" ::class="open && 'rotate-90'" />
                            <span class="min-w-0">
                                <span class="block truncate font-mono text-sm text-zinc-900 dark:text-white">{{ $webhook->url }}</span>
                                <span class="mt-1 block text-xs text-zinc-500">
                                    {{ trans_choice(':count event|:count events', count($webhook->events)) }}
                                    · {{ $webhook->last_delivery_at ? __('last delivery :date', ['date' => $webhook->last_delivery_at->diffForHumans()]) : __('no delivery yet') }}
                                </span>
                            </span>
                        </button>
                        @if ($webhook->enabled)
                            <x-ui.badge color="green" dot>{{ __('Active') }}</x-ui.badge>
                        @elseif ($webhook->disabled_reason === 'failures')
                            <x-ui.badge color="red" dot :title="__('Disabled after :count failed events in a row.', ['count' => \App\Models\Webhook::MAX_FAILURES])">{{ __('Disabled: failures') }}</x-ui.badge>
                        @else
                            <x-ui.badge dot>{{ __('Disabled') }}</x-ui.badge>
                        @endif
                    </div>

                    <div x-show="open" x-collapse x-cloak class="space-y-6 border-t border-zinc-100 bg-zinc-50/60 px-5 py-5 sm:px-6 dark:border-zinc-800 dark:bg-white/[0.02]">
                        @if ($canConfigure)
                            <div x-data="{ editing: @js($open === $webhook->id && $errors->hasAny(['url', 'events'])) }">
                                <div x-show="! editing" class="flex flex-wrap items-start justify-between gap-4">
                                    <x-settings.summary class="min-w-0 flex-1" :columns="1">
                                        <x-settings.value :label="__('Endpoint URL')" :value="$webhook->url" mono />
                                        <x-settings.value :label="__('Events')">
                                            <span class="flex flex-wrap gap-1.5">
                                                @foreach ($labels as $event => [$label])
                                                    @if ($webhook->listensTo($event))<x-ui.badge>{{ $label }}</x-ui.badge>@endif
                                                @endforeach
                                            </span>
                                        </x-settings.value>
                                    </x-settings.summary>
                                    <x-ui.button type="button" variant="secondary" size="sm" icon="pencil" x-on:click="editing = true">{{ __('Edit') }}</x-ui.button>
                                </div>
                                <form method="POST" action="{{ route('projects.webhooks.update', [$project, $webhook]) }}" class="space-y-4" x-show="editing" x-cloak>
                                    @csrf
                                    @method('PUT')
                                    <x-ui.input name="url" type="url" :id="'url-'.$webhook->id" :label="__('Endpoint URL')" :value="$webhook->url" required />
                                    <fieldset>
                                        <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Events') }}</legend>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @foreach ($labels as $event => [$label, $text])
                                                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-zinc-200 bg-white p-3 transition has-checked:border-brand-500 dark:border-zinc-800 dark:bg-zinc-900">
                                                    <input type="checkbox" name="events[]" value="{{ $event }}" @checked($webhook->listensTo($event)) class="mt-0.5">
                                                    <span class="min-w-0"><span class="block text-sm font-medium text-zinc-900 dark:text-white">{{ $label }}</span><span class="block font-mono text-[11px] text-zinc-500">{{ $event }}</span></span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                    <div class="flex gap-2">
                                        <x-ui.button size="sm" icon="check">{{ __('Save') }}</x-ui.button>
                                        <x-ui.button type="button" size="sm" variant="ghost" x-on:click="editing = false">{{ __('Cancel') }}</x-ui.button>
                                    </div>
                                </form>
                            </div>

                            <div>
                                <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Signing secret') }}</p>
                                <div class="flex flex-wrap items-center gap-2" x-data="{ shown: false, copied: false }">
                                    <code class="min-w-0 flex-1 truncate rounded-md border border-zinc-200 bg-white px-3 py-2 font-mono text-xs text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300" x-text="shown ? @js($webhook->secret) : 'whsec_••••••••••••••••••••'">whsec_••••••••••••••••••••</code>
                                    <x-ui.button type="button" variant="secondary" size="sm" x-on:click="shown = !shown"><span x-text="shown ? @js(__('Hide')) : @js(__('Show'))">{{ __('Show') }}</span></x-ui.button>
                                    <x-ui.button type="button" variant="secondary" size="sm" icon="copy" x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($webhook->secret) }}); copied = true; setTimeout(() => copied = false, 1500)"><span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span></x-ui.button>
                                    <form method="POST" action="{{ route('projects.webhooks.rotate', [$project, $webhook]) }}" data-confirm-title="{{ __('Generate a new secret?') }}" data-confirm="{{ __('Your server must use the new one to verify the next events.') }}" data-confirm-action="{{ __('Generate') }}">
                                        @csrf
                                        <x-ui.button variant="ghost" size="sm" icon="refresh">{{ __('Regenerate') }}</x-ui.button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        <div>
                            <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Recent attempts') }}</p>
                            @if ($webhook->deliveries->isEmpty())
                                <p class="text-sm text-zinc-500">{{ __('No attempt yet. Send a test event to check your endpoint.') }}</p>
                            @else
                                <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                    <table class="w-full min-w-[480px] text-sm">
                                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                            @foreach ($webhook->deliveries as $delivery)
                                                <tr>
                                                    <td class="py-2 pr-3 pl-3.5">
                                                        <x-ui.badge :color="$delivery->successful() ? 'green' : 'red'">{{ $delivery->status ?? __('Error') }}</x-ui.badge>
                                                    </td>
                                                    <td class="py-2 pr-3 font-mono text-xs text-zinc-700 dark:text-zinc-300">{{ $delivery->event }}</td>
                                                    <td class="max-w-56 truncate py-2 pr-3 text-xs text-zinc-500" title="{{ $delivery->error }}">{{ $delivery->error }}@if ($delivery->attempt > 1) <span class="text-zinc-400">· {{ __('attempt :n', ['n' => $delivery->attempt]) }}</span>@endif</td>
                                                    <td class="py-2 pr-3 text-right text-xs whitespace-nowrap tabular-nums text-zinc-500">{{ $delivery->duration_ms }} ms</td>
                                                    <td class="py-2 pr-3.5 text-right text-xs whitespace-nowrap text-zinc-500">{{ $delivery->created_at->diffForHumans() }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        @if ($canConfigure)
                            <div class="flex flex-wrap items-center gap-2 border-t border-zinc-200 pt-5 dark:border-zinc-800">
                                <form method="POST" action="{{ route('projects.webhooks.test', [$project, $webhook]) }}">
                                    @csrf
                                    <x-ui.button variant="secondary" size="sm" icon="send">{{ __('Send a test event') }}</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('projects.webhooks.update', [$project, $webhook]) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="enabled" value="{{ $webhook->enabled ? 0 : 1 }}">
                                    <x-ui.button variant="secondary" size="sm" :icon="$webhook->enabled ? 'pause' : 'play'">{{ $webhook->enabled ? __('Disable') : __('Enable') }}</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('projects.webhooks.destroy', [$project, $webhook]) }}" class="ml-auto" data-confirm-title="{{ __('Delete this webhook?') }}" data-confirm="{{ __('Your server will no longer receive these events.') }}" data-confirm-action="{{ __('Delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button variant="ghost-danger" size="sm" icon="trash">{{ __('Delete') }}</x-ui.button>
                                </form>
                            </div>
                        @endif
                    </div>
                </li>
            @empty
                <li><x-ui.empty icon="link" :title="__('No webhook yet')" :description="__('For example: update your database when a notification is clicked, or clean up devices that unsubscribed.')" /></li>
            @endforelse
        </ul>

        @if ($canConfigure)
            <x-slot:footer>
                <form method="POST" action="{{ route('projects.webhooks.store', $project) }}" class="w-full space-y-4" x-data="{ adding: @js($errors->has('url') || $errors->has('events')) }">
                    @csrf
                    <x-ui.button type="button" variant="secondary" icon="plus" x-show="!adding" x-on:click="adding = true">{{ __('Add a webhook') }}</x-ui.button>
                    <div x-show="adding" x-cloak class="space-y-4">
                        <x-ui.input name="url" type="url" :label="__('Endpoint URL')" placeholder="https://api.example.com/carillon/webhook" required />
                        <fieldset>
                            <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Events') }}</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                @foreach ($labels as $event => [$label, $text])
                                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-zinc-200 p-3 transition has-checked:border-brand-500 has-checked:bg-brand-50/50 dark:border-zinc-800 dark:has-checked:bg-brand-500/5">
                                        <input type="checkbox" name="events[]" value="{{ $event }}" @checked(in_array($event, old('events', ['message.sent', 'notification.clicked', 'subscription.unsubscribed']), true)) class="mt-0.5">
                                        <span class="min-w-0"><span class="block text-sm font-medium text-zinc-900 dark:text-white">{{ $label }}</span><span class="block text-xs text-zinc-500">{{ $text }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                            @error('events')<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </fieldset>
                        <div class="flex gap-2">
                            <x-ui.button icon="plus">{{ __('Add the webhook') }}</x-ui.button>
                            <x-ui.button type="button" variant="ghost" x-on:click="adding = false">{{ __('Cancel') }}</x-ui.button>
                        </div>
                    </div>
                </form>
            </x-slot:footer>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('Verifying the signature')" icon="shield">
        <p class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">{!! __('Each request carries a :header header: compute the HMAC-SHA256 of :signed with the secret and compare it with v1. Reject requests older than 5 minutes.', ['header' => '<code class="font-mono text-xs">Carillon-Signature: t=…,v1=…</code>', 'signed' => '<code class="font-mono text-xs">t + "." + body</code>']) !!}</p>
        <x-ui.code label="Node.js">import crypto from 'node:crypto';

function verify(rawBody, header, secret) {
  const { t, v1 } = Object.fromEntries(header.split(',').map((part) => part.split('=')));
  const expected = crypto.createHmac('sha256', secret).update(`${t}.${rawBody}`).digest('hex');
  const fresh = Math.abs(Date.now() / 1000 - Number(t)) < 300;
  return fresh && crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(v1));
}</x-ui.code>
        <x-ui.code label="PHP" class="mt-4">function verify(string $body, string $header, string $secret): bool
{
    parse_str(str_replace(',', '&', $header), $parts);
    $expected = hash_hmac('sha256', $parts['t'].'.'.$body, $secret);

    return abs(time() - (int) $parts['t']) < 300 && hash_equals($expected, $parts['v1'] ?? '');
}</x-ui.code>
        <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Answer with a 2xx status within 10 seconds. Failed deliveries are retried 4 times over about 40 minutes; after :count events in a row could not be delivered, the webhook is disabled.', ['count' => \App\Models\Webhook::MAX_FAILURES]) }}</p>
    </x-ui.card>
</x-projects.settings>
