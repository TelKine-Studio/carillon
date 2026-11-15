<?php

namespace App\Providers;

use App\Http\Middleware\SetLocale;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        $this->app->make(Settings::class)->applyToConfig();

        // Error pages can render before the SetLocale middleware ran (unknown
        // route, missing model): pick the language before they translate.
        View::composer(['errors::*', 'errors.*'], function () {
            try {
                $this->app->make(SetLocale::class)->apply(request(), browserFallback: true);
            } catch (\Throwable) {
                // Database down (500/503): keep the default language.
            }
        });

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Identifiers are UUIDs: anything else is a 404 (and never reaches
        // PostgreSQL, which rejects malformed UUIDs).
        $uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
        foreach (['project', 'message', 'subscriber', 'template', 'app_id', 'appId', 'id'] as $parameter) {
            Route::pattern($parameter, $uuid);
        }

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        Paginator::defaultView('pagination.default');
        Paginator::defaultSimpleView('pagination.default');

        $this->configureRateLimiting();

        // Counters and status shown in the sidebar.
        View::composer('layouts.navigation', function ($view) {
            $view->with('nav', once(function () {
                $user = auth()->user();

                return [
                    'projects' => $user ? $user->accessibleProjects()->orderBy('name')->get(['id', 'app_id', 'name', 'icon_path']) : collect(),
                    'invitations' => $user?->isAdmin() ? Invitation::query()->pending()->count() : 0,
                ];
            }));
        });

        // Long running queue workers must pick up settings changed from the
        // administration UI (e.g. a new SMTP server) without a restart.
        Event::listen(JobProcessing::class, function () {
            $settings = $this->app->make(Settings::class);
            $settings->refresh();
            $settings->applyToConfig();
            Mail::forgetMailers();
        });
    }

    protected function configureRateLimiting(): void
    {
        // Per API key: sending a message to a whole audience is one request.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(600)
            ->by('api:'.($request->attributes->get('api_key')?->id ?: $request->ip())));

        // SDK calls of the apps, per device address.
        RateLimiter::for('sdk', fn (Request $request) => Limit::perMinute(120)->by('sdk:'.$request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
