<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->isOnboarded() && ! $request->routeIs('onboarding') && ! $request->routeIs('onboarding.*')) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
