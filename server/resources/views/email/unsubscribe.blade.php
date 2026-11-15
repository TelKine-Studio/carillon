@php
    $project = $subscription->project;
    $address = (string) $subscription->token;
    [$local, $domain] = array_pad(explode('@', $address, 2), 2, '');
    $masked = mb_substr($local, 0, 2).str_repeat('•', max(1, mb_strlen($local) - 2)).'@'.$domain;
    $resubscribed ??= false;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>{{ $done ? __('Unsubscribed') : __('Unsubscribe') }} · {{ $project->name }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>{!! file_get_contents(resource_path('js/theme-init.js')) !!}</script>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-white">
    <main class="flex min-h-full items-center justify-center px-4 py-16">
        <div class="w-full max-w-sm">
            <div class="card p-6 text-center sm:p-8">
                @if ($project->icon_path)
                    <img src="{{ \App\Http\Controllers\Sdk\ConfigController::iconUrl($project) }}" alt="" class="mx-auto size-12 rounded-xl object-cover">
                @else
                    <span class="mx-auto grid size-12 place-items-center rounded-xl bg-brand-50 text-lg font-semibold text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">{{ mb_strtoupper(mb_substr($project->name, 0, 1)) }}</span>
                @endif
                @if ($done)
                    <h1 class="mt-5 text-xl font-semibold tracking-tight">{{ __('You are unsubscribed') }}</h1>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __(':address will no longer receive emails from :project.', ['address' => $masked, 'project' => $project->name]) }}</p>
                    <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6">
                        <input type="hidden" name="resubscribe" value="1">
                        <x-ui.button variant="secondary" class="w-full">{{ __('Subscribe again') }}</x-ui.button>
                    </form>
                @else
                    <h1 class="mt-5 text-xl font-semibold tracking-tight">{{ $resubscribed ? __('You are subscribed again') : __('Unsubscribe from :project', ['project' => $project->name]) }}</h1>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $resubscribed ? __(':address receives the emails of :project again.', ['address' => $masked, 'project' => $project->name]) : __(':address will no longer receive emails from :project.', ['address' => $masked, 'project' => $project->name]) }}
                    </p>
                    @unless ($resubscribed)
                        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6">
                            <x-ui.button class="w-full">{{ __('Unsubscribe') }}</x-ui.button>
                        </form>
                    @endunless
                @endif
            </div>
            <p class="mt-6 flex items-center justify-center gap-2 text-xs text-zinc-400">
                <x-ui.logo class="size-4 rounded-[3px] ring-0 shadow-none" />{{ __('Sent with Carillon') }}
            </p>
        </div>
    </main>
</body>
</html>
