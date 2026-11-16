@php
    $mail = $project->credential('email');
    $driver = old('driver', $mail?->detail('driver', 'smtp') ?? 'smtp');
    $drivers = [
        'smtp' => ['SMTP', __('Any mail server: yours, Gmail, Brevo, OVH…')],
        'resend' => ['Resend', __('Simple API, good deliverability.')],
        'ses' => ['Amazon SES', __('The cheapest at volume.')],
        'postmark' => ['Postmark', __('Excellent for transactional emails.')],
        'mailgun' => ['Mailgun', __('API and analytics, US or EU.')],
        'direct' => [__('Direct delivery'), __('From this server, without a third party.')],
    ];
    $webhook = $mail && in_array($mail->detail('driver'), \App\Email\Drivers::WEBHOOKS, true)
        ? url('/api/v1/email/'.$project->app_id.'/'.$mail->detail('driver').'/'.$mail->detail('webhook_secret'))
        : null;
    $isDirect = $mail?->detail('driver') === 'direct';
    $domain = $mail?->detail('dkim_domain');
@endphp

<form method="POST" action="{{ route('projects.email.update', $project) }}" x-data="{ driver: @js($driver) }">
    @csrf
    @method('PUT')
    <x-settings.section :title="__('Email')" :description="__('Send emails to your audience, with the service of your choice.')" icon="mail"
        :editable="$canConfigure" :empty="! $mail"
        :fields="['driver', 'from_address', 'from_name', 'host', 'port', 'encryption', 'username', 'secret', 'aws_key_id', 'region', 'domain', 'message_stream', 'helo', 'rate', 'reply_to', 'tracking']">
        <x-slot:status>
            @if ($mail?->isUsable())
                <x-ui.badge color="green" dot>{{ \App\Http\Controllers\EmailSettingsController::label($mail->detail('driver')) }}</x-ui.badge>
            @else
                <x-ui.badge color="zinc">{{ __('Not configured') }}</x-ui.badge>
            @endif
        </x-slot:status>
        <x-slot:summary>
            @if ($mail)
                <x-settings.summary>
                    <x-settings.value :label="__('Sending method')" :value="$drivers[$mail->detail('driver')][0] ?? $mail->detail('driver')" />
                    <x-settings.value :label="__('Sender')" :value="trim(($mail->detail('from_name') ? $mail->detail('from_name').' ' : '').'<'.$mail->detail('from_address').'>')" />
                    @if ($mail->detail('driver') === 'smtp')
                        <x-settings.value :label="__('Server')" :value="$mail->detail('host').($mail->detail('port') ? ':'.$mail->detail('port') : '')" mono />
                    @elseif (in_array($mail->detail('driver'), ['ses', 'mailgun'], true))
                        <x-settings.value :label="__('Region')" :value="$mail->detail('region')" mono />
                    @endif
                    @if ($mail->detail('domain'))
                        <x-settings.value :label="__('Domain')" :value="$mail->detail('domain')" mono />
                    @endif
                    <x-settings.value :label="__('Open and click tracking')" :on="(bool) $mail->detail('tracking', true)" />
                    <x-settings.value :label="__('Sending rate')" :value="$mail->detail('rate') ? trans_choice(':count email per second|:count emails per second', (int) $mail->detail('rate')) : null" :empty="__('Default of the service')" />
                </x-settings.summary>
                @if ($mail->isUsable())
            <div class="mt-5 space-y-5 border-t border-zinc-100 pt-6 dark:border-zinc-800">
                @if ($isDirect && $domain)
                    <div>
                        <p class="mb-1 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('DNS records of :domain', ['domain' => $domain]) }}</p>
                        <p class="mb-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add them at your domain registrar, so that receiving servers trust your emails.') }}</p>
                        <div class="space-y-3">
                            <x-ui.code :label="'DKIM · TXT · '.$mail->detail('dkim_selector').'._domainkey.'.$domain">{{ \App\Http\Controllers\EmailSettingsController::dkimRecord($mail) }}</x-ui.code>
                            <x-ui.code :label="'SPF · TXT · '.$domain">v=spf1 a mx ip4:{{ __('IP_OF_THIS_SERVER') }} ~all</x-ui.code>
                            <x-ui.code :label="'DMARC · TXT · _dmarc.'.$domain">v=DMARC1; p=none; rua=mailto:postmaster@{{ $domain }}</x-ui.code>
                        </div>
                    </div>
                @endif

                @if ($webhook)
                    <div>
                        <p class="mb-1 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Bounces and complaints') }}</p>
                        <p class="mb-3 text-sm text-zinc-500 dark:text-zinc-400">
                            @switch($mail->detail('driver'))
                                @case('resend') {{ __('In Resend → Webhooks, add this address with the email.bounced and email.complained events.') }} @break
                                @case('postmark') {{ __('In Postmark → your server → Webhooks, add this address with Bounce and Spam complaint.') }} @break
                                @case('mailgun') {{ __('In Mailgun → Webhooks, use this address for Permanent failure, Spam complaints and Unsubscribes.') }} @break
                                @case('ses') {{ __('In Amazon SNS, create a topic with an HTTPS subscription to this address, then send the bounces and complaints of SES to it (confirmed automatically).') }} @break
                            @endswitch
                            {{ __('Dead addresses then stop receiving emails, which protects your reputation.') }}
                        </p>
                        <x-ui.code :label="__('Webhook address')">{{ $webhook }}</x-ui.code>
                    </div>
                @endif

                @if ($canConfigure)
                    <div>
                        <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Send a test email') }}</p>
                        <div class="flex flex-wrap items-start gap-2">
                            <input form="email-test" type="email" name="to" required value="{{ auth()->user()->email }}" class="form-control min-w-0 flex-1 basis-56" aria-label="{{ __('Address') }}">
                            <x-ui.button form="email-test" variant="secondary" icon="send">{{ __('Send') }}</x-ui.button>
                            @if ($isDirect)
                                <x-ui.button form="email-port" variant="secondary" icon="shield">{{ __('Check port 25') }}</x-ui.button>
                            @endif
                        </div>
                        @if ($mail->last_error)
                            <p class="mt-2 flex items-start gap-2 text-sm text-red-700 dark:text-red-400"><x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />{{ $mail->last_error }}</p>
                        @elseif ($mail->verified_at)
                            <p class="mt-2 text-xs text-zinc-500">{{ __('Last test sent :date.', ['date' => $mail->verified_at->diffForHumans()]) }}</p>
                        @endif
                    </div>
                @endif
            </div>
                @endif

            @else
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Choose how emails leave: SMTP, Resend, Amazon SES, Postmark, Mailgun or direct delivery.') }}</p>
            @endif
        </x-slot:summary>

        <fieldset @disabled(! $canConfigure) class="space-y-6">
            <div>
                <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('How emails are sent') }}</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($drivers as $key => [$label, $text])
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 p-3 transition has-checked:border-brand-500 has-checked:bg-brand-50/50 dark:border-zinc-800 dark:has-checked:bg-brand-500/5">
                            <input type="radio" name="driver" value="{{ $key }}" x-model="driver" class="mt-0.5">
                            <span class="min-w-0"><span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ $label }}</span><span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $text }}</span></span>
                        </label>
                    @endforeach
                </div>
                @error('driver')<p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="from_address" type="email" id="mail-from" :label="__('Sender address')" :value="old('from_address', $mail?->detail('from_address'))" placeholder="news@example.com" :hint="__('Its domain must be verified with your service (or set up below for direct delivery).')" />
                <x-ui.input name="from_name" id="mail-from-name" :label="__('Sender name')" :value="old('from_name', $mail?->detail('from_name'))" :placeholder="$project->name" optional />
            </div>

            {{-- SMTP --}}
            <div x-show="driver === 'smtp'" x-cloak class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="host" id="mail-host" :label="__('Server')" :value="old('host', $mail?->detail('host'))" placeholder="smtp.example.com" />
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.input name="port" type="number" id="mail-port" :label="__('Port')" :value="old('port', $mail?->detail('port', 587))" />
                    <x-ui.select name="encryption" id="mail-encryption" :label="__('Security')" :value="old('encryption', $mail?->detail('encryption', 'tls'))" :options="['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => __('None')]" />
                </div>
                <x-ui.input name="username" id="mail-username" :label="__('Username')" :value="old('username', $mail?->detail('username'))" autocomplete="off" optional />
                <x-ui.input name="secret" type="password" id="mail-password" :label="__('Password')" autocomplete="new-password" :placeholder="$mail?->detail('driver') === 'smtp' && $mail?->secret ? '••••••••' : ''" :hint="__('Leave empty to keep the current one.')" optional x-bind:disabled="driver !== 'smtp'" />
            </div>

            {{-- API services --}}
            <div x-show="['resend', 'postmark'].includes(driver)" x-cloak class="grid gap-5 sm:grid-cols-2">
                <x-ui.input name="secret" type="password" id="mail-api-key" :label="__('API key')" autocomplete="new-password" :placeholder="in_array($mail?->detail('driver'), ['resend', 'postmark'], true) ? '••••••••' : ''" x-bind:disabled="!['resend', 'postmark'].includes(driver)" />
                <div x-show="driver === 'postmark'">
                    <x-ui.input name="message_stream" id="mail-stream" :label="__('Message stream')" :value="old('message_stream', $mail?->detail('message_stream'))" placeholder="broadcast" :hint="__('Use a broadcast stream for marketing emails.')" optional />
                </div>
            </div>
            <div x-show="driver === 'ses'" x-cloak class="grid gap-5 sm:grid-cols-3">
                <x-ui.input name="aws_key_id" id="mail-aws-key" :label="__('Access key ID')" :value="old('aws_key_id', $mail?->detail('driver') === 'ses' ? $mail?->detail('key_id') : null)" placeholder="AKIA…" />
                <x-ui.input name="secret" type="password" id="mail-aws-secret" :label="__('Secret access key')" autocomplete="new-password" :placeholder="$mail?->detail('driver') === 'ses' ? '••••••••' : ''" x-bind:disabled="driver !== 'ses'" />
                <x-ui.input name="region" id="mail-aws-region" :label="__('Region')" :value="old('region', $mail?->detail('driver') === 'ses' ? $mail?->detail('region') : 'eu-west-3')" placeholder="eu-west-3" x-bind:disabled="driver !== 'ses'" />
            </div>
            <div x-show="driver === 'mailgun'" x-cloak class="grid gap-5 sm:grid-cols-3">
                <x-ui.input name="secret" type="password" id="mail-mg-key" :label="__('API key')" autocomplete="new-password" :placeholder="$mail?->detail('driver') === 'mailgun' ? '••••••••' : ''" x-bind:disabled="driver !== 'mailgun'" />
                <x-ui.input name="domain" id="mail-mg-domain" :label="__('Sending domain')" :value="old('domain', $mail?->detail('domain'))" placeholder="mg.example.com" />
                <x-ui.select name="region" id="mail-mg-region" :label="__('Region')" :value="old('region', $mail?->detail('driver') === 'mailgun' ? $mail?->detail('region') : 'eu')" :options="['eu' => __('Europe'), 'us' => __('United States')]" x-bind:disabled="driver !== 'mailgun'" />
            </div>

            {{-- Direct delivery --}}
            <div x-show="driver === 'direct'" x-cloak class="space-y-4">
                <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                    <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                    <span>{{ __('This server delivers to the recipients’ mail servers itself. It needs outgoing port 25 (often blocked by hosting providers), a reverse DNS for its IP address, and a few weeks of small volumes before Gmail and Outlook trust it. For large volumes, prefer a service.') }}</span>
                </div>
                <x-ui.input name="helo" id="mail-helo" :label="__('Server name')" :value="old('helo', $mail?->detail('helo'))" placeholder="mail.example.com" :hint="__('Announced to the receiving servers; the reverse DNS of the IP address of this server must give this name.')" optional />
            </div>

            <details class="group" @if ($errors->hasAny(['rate', 'reply_to'])) open @endif>
                <summary class="flex cursor-pointer items-center gap-2 text-sm font-medium text-zinc-600 select-none dark:text-zinc-400">
                    <x-ui.icon name="chevron-right" class="size-4 text-zinc-400 transition group-open:rotate-90" />{{ __('Advanced options') }}
                </summary>
                <div class="mt-4 space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="reply_to" type="email" id="mail-reply" :label="__('Reply-to address')" :value="old('reply_to', $mail?->detail('reply_to'))" placeholder="support@example.com" optional />
                        <x-ui.input name="rate" type="number" min="1" max="500" id="mail-rate" :label="__('Emails per second')" :value="old('rate', $mail?->detail('rate'))" ::placeholder="({{ json_encode(\App\Email\Drivers::DEFAULT_RATE) }})[driver]" :hint="__('Under the limit of your service plan.')" optional />
                    </div>
                    <x-ui.toggle name="tracking" :checked="$mail ? $mail->detail('tracking', true) !== false : true" :label="__('Track opens and clicks')" :description="__('An invisible image and redirected links measure opens and clicks. The unsubscribe link is always added.')" />
                </div>
            </details>
        </fieldset>

        @if ($mail)
            <x-slot:danger>
                <x-ui.button type="submit" form="remove-email" variant="ghost-danger" icon="trash" class="mr-auto">{{ __('Remove') }}</x-ui.button>
            </x-slot:danger>
        @endif
    </x-settings.section>
</form>

<form id="email-test" method="POST" action="{{ route('projects.email.test', $project) }}" class="hidden">@csrf</form>
<form id="email-port" method="POST" action="{{ route('projects.email.port', $project) }}" class="hidden">@csrf</form>
