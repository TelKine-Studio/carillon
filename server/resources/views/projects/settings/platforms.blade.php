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
        <x-ui.card :title="__('iOS · Apple Push Notification service')" :description="__('A .p8 key of your Apple Developer account: one key works for all your apps and for both development and App Store builds.')" icon="apple">
            <x-slot:actions>
                @if ($apns?->isUsable())<x-ui.badge color="green" dot>{{ __('Configured') }}</x-ui.badge>@else<x-ui.badge color="zinc">{{ __('Not configured') }}</x-ui.badge>@endif
            </x-slot:actions>
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
            @if ($canConfigure)
                <x-slot:footer>
                    @if ($apns)
                        <x-ui.button type="submit" form="remove-apns" variant="ghost-danger" icon="trash" class="mr-auto">{{ __('Remove') }}</x-ui.button>
                    @endif
                    <x-ui.button>{{ __('Save') }}</x-ui.button>
                </x-slot:footer>
            @endif
        </x-ui.card>
    </form>

    {{-- Android --}}
    <form method="POST" action="{{ route('projects.push.update', [$project, 'fcm']) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <x-ui.card :title="__('Android · Firebase Cloud Messaging')" :description="__('The service account key of the Firebase project of your Android app.')" icon="android">
            <x-slot:actions>
                @if ($fcm?->isUsable())<x-ui.badge color="green" dot>{{ __('Configured') }}</x-ui.badge>@else<x-ui.badge color="zinc">{{ __('Not configured') }}</x-ui.badge>@endif
            </x-slot:actions>
            @if ($fcm)
                <dl class="mb-5 grid gap-3 rounded-xl border border-zinc-100 p-4 text-sm sm:grid-cols-2 dark:border-zinc-800">
                    <div><dt class="text-xs text-zinc-500">{{ __('Firebase project') }}</dt><dd class="font-mono text-zinc-800 dark:text-zinc-200">{{ $fcm->detail('project_id') }}</dd></div>
                    <div class="min-w-0"><dt class="text-xs text-zinc-500">{{ __('Service account') }}</dt><dd class="truncate font-mono text-zinc-800 dark:text-zinc-200">{{ $fcm->detail('client_email') }}</dd></div>
                </dl>
            @endif
            <fieldset @disabled(! $canConfigure)>
                <x-ui.file name="service_account" accept="application/json,.json" :label="$fcm ? __('Replace the service account (JSON)') : __('Service account (JSON)')" :placeholder="__('Choose the JSON file')"
                    :hint="__('Firebase console → Project settings → Service accounts → Generate new private key. Stored encrypted.')" />
            </fieldset>
            @if ($canConfigure)
                <x-slot:footer>
                    @if ($fcm)
                        <x-ui.button type="submit" form="remove-fcm" variant="ghost-danger" icon="trash" class="mr-auto">{{ __('Remove') }}</x-ui.button>
                    @endif
                    <x-ui.button>{{ __('Save') }}</x-ui.button>
                </x-slot:footer>
            @endif
        </x-ui.card>
    </form>

    {{-- Web --}}
    <form method="POST" action="{{ route('projects.push.update', [$project, 'webpush']) }}" x-data="{ custom: {{ $errors->hasAny(['public_key', 'private_key']) ? 'true' : 'false' }} }">
        @csrf
        @method('PUT')
        <x-ui.card :title="__('Web · Push API')" :description="__('Notifications in Chrome, Edge, Firefox and Safari, with VAPID keys.')" icon="globe-web">
            <x-slot:actions>
                @if ($web?->isUsable())<x-ui.badge color="green" dot>{{ __('Configured') }}</x-ui.badge>@else<x-ui.badge color="zinc">{{ __('Not configured') }}</x-ui.badge>@endif
            </x-slot:actions>
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
            @if ($canConfigure)
                <x-slot:footer>
                    @if ($web)
                        <x-ui.button type="submit" form="remove-webpush" variant="ghost-danger" icon="trash" class="mr-auto">{{ __('Remove') }}</x-ui.button>
                    @endif
                    <x-ui.button>{{ __('Save') }}</x-ui.button>
                </x-slot:footer>
            @endif
        </x-ui.card>
    </form>

    @include('projects.settings.partials.email')

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

    @foreach (['apns', 'fcm', 'webpush', 'email'] as $provider)
        <form id="remove-{{ $provider }}" method="POST" action="{{ route('projects.push.destroy', [$project, $provider]) }}" class="hidden"
            data-confirm-title="{{ __('Remove these keys?') }}" data-confirm="{{ __('Devices of this platform stop receiving notifications until you add keys again.') }}" data-confirm-action="{{ __('Remove') }}">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</x-projects.settings>
