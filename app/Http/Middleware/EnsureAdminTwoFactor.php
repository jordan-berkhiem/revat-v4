<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            return $next($request);
        }

        if (! $admin->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->session()->get('admin_2fa_verified') === true) {
            return $next($request);
        }

        // Redirect to 2FA challenge
        return redirect()->route('admin.two-factor.challenge');
    }
}
