<x-layouts.app :title="__('Profile')">
    <div class="mb-8 flex flex-wrap items-center gap-5">
        <x-ui.avatar :user="$user" size="lg" class="size-16 text-xl" />
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 sm:text-[28px] dark:text-white">{{ $user->name }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-zinc-500">{{ $user->email }} <x-ui.badge :color="$user->isAdmin() ? 'brand' : 'zinc'">{{ $user->role->label() }}</x-ui.badge></p>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-[220px_minmax(0,1fr)]">
        <nav class="hidden lg:block">
            <ul class="sticky top-8 space-y-1 text-sm">
                @foreach (['account' => ['user', __('Account')], 'security' => ['lock', __('Security')]] as $anchor => [$icon, $label])
                    <li><a href="#{{ $anchor }}" class="nav-link"><x-ui.icon :name="$icon" class="size-4" />{{ $label }}</a></li>
                @endforeach
            </ul>
        </nav>

        <div class="max-w-3xl space-y-6">
            <form method="POST" action="{{ route('profile.update') }}" id="account" class="scroll-mt-24">
                @csrf
                @method('PUT')
                <x-ui.card :title="__('Account')" :description="__('Your name, email address and language.')" icon="user">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="name" :label="__('Full name')" :value="$user->name" required />
                        <x-ui.input name="email" type="email" :label="__('Email')" :value="$user->email" required />
                        <x-ui.select name="locale" :label="__('Language')" :value="$user->locale" :options="['' => __('Instance default')] + $locales" />
                    </div>
                    <x-slot:footer><x-ui.button>{{ __('Save') }}</x-ui.button></x-slot:footer>
                </x-ui.card>
            </form>

            <form method="POST" action="{{ route('profile.password') }}" id="security" class="scroll-mt-24">
                @csrf
                @method('PUT')
                <x-ui.card :title="__('Password')" :description="__('Use a long password that you do not use elsewhere.')" icon="lock">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="current_password" type="password" :label="__('Current password')" required autocomplete="current-password" class="sm:col-span-2" />
                        <x-ui.input name="password" type="password" :label="__('New password')" required autocomplete="new-password" />
                        <x-ui.input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
                    </div>
                    <x-slot:footer><x-ui.button>{{ __('Update password') }}</x-ui.button></x-slot:footer>
                </x-ui.card>
            </form>

            <x-ui.card :title="__('Two-factor authentication')" :description="__('Ask for a code from an authenticator app (Google Authenticator, 1Password, Bitwarden…) at each login.')" icon="shield">
                <x-slot:actions>
                    @if ($user->hasTwoFactor())
                        <x-ui.badge color="green" dot>{{ __('Enabled') }}</x-ui.badge>
                    @else
                        <x-ui.badge dot>{{ __('Disabled') }}</x-ui.badge>
                    @endif
                </x-slot:actions>

                @if (session('recovery_codes'))
                    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
                        <p class="mb-3 text-sm font-semibold text-amber-900 dark:text-amber-200">{{ __('Save these recovery codes somewhere safe. Each one can be used once if you lose your phone.') }}</p>
                        <x-ui.code :label="__('Recovery codes')">{{ implode("\n", session('recovery_codes')) }}</x-ui.code>
                    </div>
                @endif

                @if ($user->hasTwoFactor())
                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('two-factor.recovery-codes') }}">
                            @csrf
                            <x-ui.button variant="secondary" size="sm" icon="refresh">{{ __('New recovery codes') }}</x-ui.button>
                        </form>
                        <form method="POST" action="{{ route('two-factor.disable') }}" class="flex flex-wrap items-start gap-2" x-data="{ open: {{ $errors->has('password') ? 'true' : 'false' }} }">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="button" variant="ghost-danger" size="sm" @click="open = true" x-show="!open">{{ __('Disable') }}</x-ui.button>
                            <template x-if="open">
                                <div class="flex flex-wrap items-start gap-2">
                                    <x-ui.input name="password" type="password" :placeholder="__('Your password')" required autocomplete="current-password" />
                                    <x-ui.button variant="danger" size="sm" class="mt-1">{{ __('Disable') }}</x-ui.button>
                                </div>
                            </template>
                        </form>
                    </div>
                @elseif ($twoFactorQr)
                    <div class="grid gap-6 sm:grid-cols-[auto_1fr] sm:items-center">
                        <div class="rounded-2xl bg-white p-3 ring-1 ring-zinc-200 [&_svg]:size-44">{!! $twoFactorQr !!}</div>
                        <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-4">
                            @csrf
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Scan this QR code with your authenticator app, then enter the 6-digit code it shows.') }}</p>
                            <div x-data="copy(@js($user->two_factor_secret))" class="flex items-center gap-2 text-xs text-zinc-500">
                                {{ __('Or enter this key:') }} <code class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono dark:bg-white/5">{{ $user->two_factor_secret }}</code>
                                <button type="button" @click="copy()"><x-ui.icon name="copy" class="size-3.5" /></button>
                            </div>
                            <div class="flex items-start gap-2">
                                <x-ui.input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456" required class="w-36" />
                                <x-ui.button class="mt-0">{{ __('Activate') }}</x-ui.button>
                            </div>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('two-factor.enable') }}">
                        @csrf
                        <x-ui.button variant="secondary" icon="shield">{{ __('Set up two-factor authentication') }}</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

        </div>
    </div>
</x-layouts.app>
