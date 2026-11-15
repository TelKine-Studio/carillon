<?php

use App\Http\Middleware\EnsureApplicationIsInstalled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Carillon is meant to run behind a reverse proxy (Coolify / Traefik, ...).
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            EnsureApplicationIsInstalled::class,
            EnsureUserIsActive::class,
            SetLocale::class,
        ]);

        $middleware->api(append: [SetLocale::class]);

        // Send visitors to the first-run setup before asking them to log in.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureApplicationIsInstalled::class,
        );

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('projects.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // OneSignal error format: {"errors": ["..."]}, 400 for invalid input.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json(['errors' => array_values(Arr::flatten($e->errors()))], 400);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/v1/*')) {
                return response()->json(['errors' => [$e->getMessage() ?: Response::$statusTexts[$e->getStatusCode()] ?? 'Error']], $e->getStatusCode(), $e->getHeaders());
            }
        });
    })->create();

// All persistent data (SQLite database, logs, sessions, ...) can be moved to a
// single directory, e.g. a Docker volume mounted on /data.
if ($storage = $_SERVER['CARILLON_STORAGE_PATH'] ?? $_ENV['CARILLON_STORAGE_PATH'] ?? getenv('CARILLON_STORAGE_PATH')) {
    $app->useStoragePath($storage);
}

return $app;
