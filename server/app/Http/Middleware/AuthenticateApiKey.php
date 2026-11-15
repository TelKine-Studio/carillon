<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * REST API authentication, OneSignal style: "Authorization: Key <key>"
 * ("Basic <key>" and "Bearer <key>" are accepted too). The key belongs to
 * one project; an app_id given in the request must be that project.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization');
        $plain = preg_match('/^(?:Key|Basic|Bearer)\s+(\S+)$/i', $header, $match) ? $match[1] : null;
        $key = $plain ? ApiKey::findByPlain($plain) : null;

        if (! $key) {
            return response()->json(['errors' => ['Access denied. Please include an \'Authorization: Key <API key>\' header with a valid API key.']], 403);
        }

        $appId = $request->route('app_id') ?? $request->input('app_id') ?? $request->query('app_id');
        if ($appId !== null && $appId !== $key->project->app_id) {
            return response()->json(['errors' => ['This API key does not belong to app_id '.$appId.'.']], 403);
        }

        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('api_key', $key);
        $request->attributes->set('project', $key->project);

        return $next($request);
    }
}
