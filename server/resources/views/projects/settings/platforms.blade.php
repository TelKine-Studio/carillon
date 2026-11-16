@php
    $apns = $project->credential('apns');
    $fcm = $project->credential('fcm');
    $web = $project->credential('webpush');
@endphp
<x-projects.settings :project="$project" :section="$section">
    {{-- Apple --}}
    <form method="POST" action="{{ route('projects.push.update', [$project, 'apns']) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <x-settings.section :title="__('iOS · Apple Push Notification service')" :description="__('A .p8 key of your Apple Developer account: one key works for all your apps and for both development and App Store builds.')" icon="apple"
            :editable="$canConfigure" :empty="! $apns" :fields="['key_id', 'team_id', 'bundle_id', 'private_key']">
            <x-slot:status>
                @if ($apns?->isUsable())<x-ui.badge color="green" dot>{{ __('Configured') }}</x-ui.badge>@else<x-ui.badge color="zinc">{{ __('Not configured') }}</x-ui.badge>@endif
            </x-slot:status>
            <x-slot:summary>
                @if ($apns)
                    <x-settings.summary>
                        <x-settings.value :label="__('Key ID')" :value="$apns->detail('key_id')" mono />
                        <x-settings.value :label="__('Team ID')" :value="$apns->detail('team_id')" mono />
                        <x-settings.value :label="__('Bundle ID')" :value="$apns->detail('bundle_id')" mono />
                        <x-settings.value :label="__('Private key (.p8)')" :value="__('Stored (encrypted)')" />
                    </x-settings.summary>
                @else
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add your Apple key to send notifications to iPhone and iPad.') }}</p>
                @endif
            </x-slot:summary>
            <fieldset @disabled(! $canConfigure) class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="key_id" :label="__('Key ID')" :value="old('key_id', $apns?->detail('key_id'))" placeholder="ABC123DEFG" maxlength="10" required />
                    <x-ui.input name="team_id" :label="__('Team ID')" :value="old('team_id', $apns?->detail('team_id'))" placeholder="4ZA5MXYWG7" maxlength="10" required />
                </div>
                <x-ui.input name="bundle_id" :label="__('Bundle ID')" :value="old('bundle_id', $apns?->detail('bundle_id'))" placeholder="com.example.app" required :hint="__('The bundle identifier of your iOS app.')" />
                <x-ui.file name="private_key" accept=".p8,text/plain" :label="__('Private key (.p8)')" :placeholder="__('Choose your AuthKey_XXXXXXXXXX.p8 file')"
                    :hint="$apns ? __('A key is stored (encrypted). Leave empty to keep it.') : __('Stored encrypted.')" :optional="(bool) $apns" />
            </fieldset>
            <details class="group mt-5 rounded-xl border border-zinc-200/80 bg-zinc-50/60 p-4 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-white/[.02] dark:text-zinc-400">
                <summary class="flex cursor-pointer items-center gap-2 font-medium text-zinc-800 select-none dark:text-zinc-200">
                    <x-ui.icon name="chevron-right" class="size-4 text-zinc-400 transition group-open:rotate-90" />{{ __('How to get a key') }}
                </summary>
                <ol class="mt-3 list-decimal space-y-1.5 pl-5">
                    <li>{{ __('On developer.apple.com, open Certificates, IDs & Profiles → Keys and create a key with “Apple Push Notifications service (APNs)”.') }}</li>
                    <li>{{ __('Download the .p8 file (only once) and note its Key ID.') }}</li>
                    <li>{{ __('Your Team ID is shown in Membership details.') }}</li>
                    <li>{{ __('Already using OneSignal? Reuse the same key: existing subscribers keep receiving notifications after migrating.') }}</li>
                </ol>
            </details>
            @if ($apns)
                <x-slot:danger>
                    <x-ui.button type="submit" form="remove-apns" variant="ghost-danger" icon="trash" class="mr-auto">{{ __('Remove') }}</x-ui.button>
                </x-slot:danger>
            @endif
        </x-settings.section>
    </form>

    {{-- Android --}}
    <form method="POST" action="{{ route('projects.push.update', [$project, 'fcm']) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <x-settings.section :title="__('Android · Firebase Cloud Messaging')" :description="__('The service account key of the Firebase project of your Android app.')" icon="android"
            :editable="$canConfigure" :empty="! $fcm" :fields="['service_account']">
            <x-slot:status>
                @if ($fcm?->isUsable())<x-ui.badge color="green" dot>{{ __('Configured') }}</x-ui.badge>@else<x-ui.badge color="zinc">{{ __('Not configured') }}</x-ui.badge>@endif
            </x-slot:status>
            <x-slot:summary>
                @if ($fcm)
                    <x-settings.summary>
                        <x-settings.value :label="__('Firebase project')" :value="$fcm->detail('project_id')" mono />
                        <x-settings.value :label="__('Service account')" :value="$fcm->detail('client_email')" mono />
                    </x-settings.summary>
                @else
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Add the key of your Firebase project to send notifications to Android phones.') }}</p>
                @endif
            </x-slot:summary>
            <fieldset @disabled(! $canConfigure)>
                <x-ui.file name="service_account" accept="application/json,.json" :label="$fcm ? __('Replace the service account (JSON)') : __('Service account (JSON)')" :placeholder="__('Choose the JSON file')"
                    :hint="__('Firebase console → Project settings → Service accounts → Generate new private key. Stored encrypted.')" />
            </fieldset>
            @if ($fcm)
                <x-slot:danger>
                    <x-ui.button type="submit" form="remove-fcm" variant="ghost-danger" icon="trash" class="mr-auto">{{ __('Remove') }}</x-ui.button>
                </x-slot:danger>
            @endif
        </x-settings.section>
    </form>

    {{-- Web --}}
    <form method="POST" action="{{ route('projects.push.update', [$project, 'webpush']) }}" x-data="{ custom: {{ $errors->hasAny(['public_key', 'private_key']) ? 'true' : 'false' }} }">
        @csrf
        @method('PUT')
        <x-settings.section :title="__('Web · Push API')" :description="__('Notifications in Chrome, Edge, Firefox and Safari, with VAPID keys.')" icon="globe-web"
            :editable="$canConfigure" :empty="! $web" :fields="['subject', 'public_key', 'private_key']">
            <x-slot:status>
                @if ($web?->isUsable())<x-ui.badge color="green" dot>{{ __('Configured') }}</x-ui.badge>@else<x-ui.badge color="zinc">{{ __('Not configured') }}</x-ui.badge>@endif
            </x-slot:status>
            <x-slot:summary>
                @if ($web)
                    <x-settings.summary>
                        <x-settings.value :label="__('Contact')" :value="$web->detail('subject')" />
                        <x-settings.value :label="__('Public key (VAPID)')" :value="$web->detail('public_key')" mono wide />
                    </x-settings.summary>
                @else
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Set up web push to send notifications to the visitors of your website.') }}</p>
                @endif
            </x-slot:summary>
            <fieldset @disabled(! $canConfigure) class="space-y-5">
                <x-ui.input name="subject" :label="__('Contact')" :value="old('subject', $web?->detail('subject') ?? 'mailto:'.auth()->user()->email)" required :hint="__('mailto: or https: address given to browser vendors in case of a problem.')" />
                @if ($web)
                    <x-ui.code :label="__('Public key (VAPID)')">{{ $web->detail('public_key') }}</x-ui.code>
                @else
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('A key pair is generated when you save.') }}</p>
                @endif
                <button type="button" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-300" x-on:click="custom = !custom" x-show="!custom">{{ __('Use existing VAPID keys') }}</button>
                <div x-show="custom" x-cloak class="grid gap-5 sm:grid-cols-2">
                    <x-ui.input name="public_key" :label="__('Public key')" optional />
                    <x-ui.input name="private_key" type="password" :label="__('Private key')" optional />
                    <p class="text-xs text-zinc-500 sm:col-span-2">{{ __('Browsers subscribed with other keys must subscribe again: OneSignal does not export its web push keys.') }}</p>
                </div>
            </fieldset>
            @if ($web)
                <x-slot:danger>
                    <x-ui.button type="submit" form="remove-webpush" variant="ghost-danger" icon="trash" class="mr-auto">{{ __('Remove') }}</x-ui.button>
                </x-slot:danger>
            @endif
        </x-settings.section>
    </form>

    @include('projects.settings.partials.email')

    {{-- SMS --}}
    @php
        $sms = $project->credential('sms');
        $smsDriver = old('driver', $sms?->detail('driver', 'twilio') ?? 'twilio');
        $smsProviders = ['twilio' => 'Twilio', 'orange' => 'Orange'];
        $same = fn ($driver) => $sms && $sms->detail('driver', 'twilio') === $driver;
    @endphp
    <form method="POST" action="{{ route('projects.push.update', [$project, 'sms']) }}" x-data="{ driver: @js($smsDriver) }">
        @csrf
        @method('PUT')
        <x-settings.section :title="__('SMS')" :description="__('Text messages to the phone numbers of your audience, with Twilio or Orange.')" icon="message"
            :editable="$canConfigure" :empty="! $sms" :fields="['driver', 'account_sid', 'from', 'auth_token', 'client_id', 'client_secret', 'sender_address', 'sender_name']">
            <x-slot:status>
                @if ($sms?->isUsable())<x-ui.badge color="green" dot>{{ $smsProviders[$sms->detail('driver', 'twilio')] ?? __('Configured') }}</x-ui.badge>@else<x-ui.badge color="zinc">{{ __('Not configured') }}</x-ui.badge>@endif
            </x-slot:status>
            <x-slot:summary>
                @if ($sms)
                    <x-settings.summary>
                        <x-settings.value :label="__('Provider')" :value="$smsProviders[$sms->detail('driver', 'twilio')] ?? $sms->detail('driver')" />
                        @if ($sms->detail('driver') === 'orange')
                            <x-settings.value :label="__('Client ID')" :value="$sms->detail('client_id')" mono />
                            <x-settings.value :label="__('Sender')" :value="trim($sms->detail('sender_address').($sms->detail('sender_name') ? ' · '.$sms->detail('sender_name') : ''))" mono />
                        @else
                            <x-settings.value :label="__('Account SID')" :value="$sms->detail('account_sid')" mono />
                            <x-settings.value :label="__('Sender')" :value="$sms->detail('from')" mono />
                        @endif
                    </x-settings.summary>
                    @if ($sms->last_error)
                        <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ __('Last error: :error', ['error' => $sms->last_error]) }}</p>
                    @endif
                @else
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Connect Twilio or Orange to send SMS: the numbers come from the SDK (addSms), the API or the OneSignal import.') }}</p>
                @endif
            </x-slot:summary>
            <fieldset @disabled(! $canConfigure) class="space-y-5">
                <input type="hidden" name="driver" :value="driver">
                <div class="grid grid-cols-2 gap-1 rounded-lg bg-zinc-100 p-1 text-sm dark:bg-white/5">
                    @foreach ($smsProviders as $key => $label)
                        <button type="button" x-on:click="driver = '{{ $key }}'" class="rounded-md py-1.5 font-medium transition"
                            :class="driver === '{{ $key }}' ? 'bg-white text-zinc-900 shadow-(--shadow-soft) dark:bg-zinc-800 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'">{{ $label }}</button>
                    @endforeach
                </div>
                <template x-if="driver === 'twilio'">
                    <div class="space-y-5">
                        <x-ui.input name="account_sid" :label="__('Account SID')" :value="old('account_sid', $sms?->detail('account_sid'))" required placeholder="AC…" :hint="__('On the home page of the Twilio console.')" />
                        <x-ui.input name="auth_token" type="password" :label="__('Auth token')" :hint="$same('twilio') ? __('Leave empty to keep the current one.') : __('Next to the Account SID, in the Twilio console.')" :required="! $same('twilio')" autocomplete="off" />
                        <x-ui.input name="from" :label="__('Sender')" :value="old('from', $sms?->detail('from'))" required placeholder="+2250700000000" :hint="__('A Twilio number, a Messaging Service SID (MG…) or a sender name where the country allows it.')" />
                    </div>
                </template>
                <template x-if="driver === 'orange'">
                    <div class="space-y-5">
                        <x-ui.input name="client_id" :label="__('Client ID')" :value="old('client_id', $sms?->detail('client_id'))" required :hint="__('In My Apps, on developer.orange.com, for your app with the SMS API.')" />
                        <x-ui.input name="client_secret" type="password" :label="__('Client secret')" :hint="$same('orange') ? __('Leave empty to keep the current one.') : __('Next to the client ID, in My Apps.')" :required="! $same('orange')" autocomplete="off" />
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-ui.input name="sender_address" :label="__('Sender number')" :value="old('sender_address', $sms?->detail('sender_address'))" required placeholder="+2250000" :hint="__('The number of your Orange contract (country code followed by 0000).')" />
                            <x-ui.input name="sender_name" :label="__('Sender name')" :value="old('sender_name', $sms?->detail('sender_name'))" maxlength="11" optional :hint="__('If Orange validated one for your contract.')" />
                        </div>
                    </div>
                </template>
            </fieldset>
            @if ($sms)
                <x-slot:danger>
                    <x-ui.button type="submit" form="remove-sms" variant="ghost-danger" icon="trash" class="mr-auto">{{ __('Remove') }}</x-ui.button>
                </x-slot:danger>
            @endif
        </x-settings.section>
    </form>

    @if ($web?->isUsable())
        @php $origin = rtrim(url('/'), '/'); @endphp
        <x-ui.card :title="__('Add notifications to your website')" icon="code">
            <ol class="space-y-5 text-sm text-zinc-600 dark:text-zinc-400">
                <li>
                    <p class="mb-2"><span class="font-semibold text-zinc-900 dark:text-white">1.</span> {!! __('Serve a :file file at the root of your site, with this line:', ['file' => '<code class="font-mono text-xs">/carillon-sw.js</code>']) !!}</p>
                    <x-ui.code label="carillon-sw.js">importScripts('{{ $origin }}/sdk/carillon-sw.js');</x-ui.code>
                </li>
                <li>
                    <p class="mb-2"><span class="font-semibold text-zinc-900 dark:text-white">2.</span> {{ __('Load the SDK in your pages, then ask for the permission after a click of the visitor:') }}</p>
                    <x-ui.code label="HTML">&lt;script src="{{ $origin }}/sdk/carillon.js" defer&gt;&lt;/script&gt;
&lt;script&gt;
  window.CarillonDeferred = window.CarillonDeferred || [];
  CarillonDeferred.push(async (Carillon) =&gt; {
    await Carillon.init({ appId: '{{ $project->app_id }}' });
    // Carillon.login('user-42'), Carillon.user.addTags({ plan: 'pro' })…
  });
  // document.querySelector('#notify').onclick = () =&gt; Carillon.notifications.requestPermission();
&lt;/script&gt;</x-ui.code>
                </li>
            </ol>
            <p class="mt-4 text-xs text-zinc-500">{{ __('Your site must use HTTPS (localhost works for development).') }}</p>
        </x-ui.card>
    @endif

    @foreach (['apns', 'fcm', 'webpush', 'email', 'sms'] as $provider)
        <form id="remove-{{ $provider }}" method="POST" action="{{ route('projects.push.destroy', [$project, $provider]) }}" class="hidden"
            data-confirm-title="{{ __('Remove these keys?') }}" data-confirm="{{ __('Devices of this platform stop receiving notifications until you add keys again.') }}" data-confirm-action="{{ __('Remove') }}">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</x-projects.settings>
