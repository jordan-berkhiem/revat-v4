<?php

namespace App\Http\Middleware;

use App\Events\OrganizationSwitched;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if impersonating — org is already set by HandleImpersonation
        if ($request->attributes->get('impersonating')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Check if user is deactivated
        if ($user->isDeactivated()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        $previousOrgId = $user->current_organization_id;
        $organization = null;

        // Try current_organization_id first
        if ($user->current_organization_id) {
            $organization = $user->organizations()
                ->where('organizations.id', $user->current_organization_id)
                ->first();
        }

        // Fall back to most recent org
        if (! $organization) {
            $organization = $user->organizations()
                ->orderByPivot('updated_at', 'desc')
                ->first();
        }

        // No org at all — redirect to org selection
        if (! $organization) {
            $user->current_organization_id = null;
            $user->save();

            if ($request->routeIs('organization.select')) {
                return $next($request);
            }

            return redirect()->route('organization.select');
        }

        // Update current_organization_id if it changed
        if ($user->current_organization_id !== $organization->id) {
            $user->current_organization_id = $organization->id;
            $user->save();
        }

        // Set Spatie team
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $user->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        // Dispatch event if org changed
        if ($previousOrgId !== $organization->id) {
            event(new OrganizationSwitched(
                user_id: $user->id,
                from_organization_id: $previousOrgId,
                to_organization_id: $organization->id,
                ip_address: $request->ip(),
                occurred_at: now(),
            ));
        }

        return $next($request);
    }
}
