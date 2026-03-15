<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Skip if impersonating
        if ($request->attributes->get('impersonating')) {
            return $next($request);
        }

        // If user has 2FA enabled, check if session is verified
        if ($user->hasTwoFactorEnabled()) {
            if ($request->session()->get('2fa_verified')) {
                return $next($request);
            }

            return redirect()->route('two-factor.challenge');
        }

        // If user does NOT have 2FA enabled but org requires it
        $organization = $user->currentOrganization;
        if ($organization && $organization->require_2fa) {
            return redirect()->route('two-factor.setup')
                ->with('2fa_enforcement', 'Your organization requires two-factor authentication.');
        }

        return $next($request);
    }
}
