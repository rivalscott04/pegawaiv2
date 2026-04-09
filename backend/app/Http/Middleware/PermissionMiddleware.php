<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $keys): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $allowed = collect(explode('|', $keys))
            ->map(fn ($r) => trim($r))
            ->filter()
            ->values();

        if ($allowed->isEmpty()) {
            return $next($request);
        }

        foreach ($allowed as $key) {
            if ($user->hasPermission($key)) {
                return $next($request);
            }
        }

        abort(403, 'Forbidden');
    }
}
