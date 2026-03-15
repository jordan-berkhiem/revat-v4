<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class HandleImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        $impersonatingUserId = $request->session()->get('impersonating_user_id');
        $impersonatingAdminId = $request->session()->get('impersonating_admin_id');
        $impersonatingOrgId = $request->session()->get('impersonating_organization_id');

        if (! $impersonatingUserId || ! $impersonatingAdminId || ! $impersonatingOrgId) {
            return $next($request);
        }

        // Validate admin is still authenticated and matches
        $admin = Auth::guard('admin')->user();
        if (! $admin || $admin->id != $impersonatingAdminId) {
            $request->session()->forget([
                'impersonating_user_id',
                'impersonating_admin_id',
                'impersonating_organization_id',
            ]);

            return redirect('/admin');
        }

        // Load the target user
        $user = User::find($impersonatingUserId);
        if (! $user) {
            $request->session()->forget([
                'impersonating_user_id',
                'impersonating_admin_id',
                'impersonating_organization_id',
            ]);

            return redirect('/admin');
        }

        // Set transient impersonation property
        $user->impersonating = true;

        // Set user on web guard
        Auth::guard('web')->setUser($user);
        Auth::shouldUse('web');

        // Set Spatie permission team context to impersonated organization
        app(PermissionRegistrar::class)->setPermissionsTeamId($impersonatingOrgId);

        // Set request attribute for easy checking
        $request->attributes->set('impersonating', true);

        return $next($request);
    }
}
