<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(Request $request)
    {
        // Validate signed URL
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        $adminId = $request->input('admin_id');
        $userId = $request->input('user_id');
        $organizationId = $request->input('organization_id');

        // Validate admin is authenticated on admin guard and matches admin_id
        $admin = Auth::guard('admin')->user();
        if (! $admin || $admin->id != $adminId) {
            abort(403, 'Admin authentication mismatch.');
        }

        // Validate organization has support_access_enabled
        $organization = Organization::find($organizationId);
        if (! $organization || ! $organization->support_access_enabled) {
            abort(403, 'Support access is not enabled for this organization.');
        }

        // Load target user and verify they belong to the organization
        $user = User::find($userId);
        if (! $user || ! $user->organizations()->where('organizations.id', $organizationId)->exists()) {
            abort(404, 'User not found or does not belong to this organization.');
        }

        // Store session flags
        $request->session()->put('impersonating_user_id', (int) $userId);
        $request->session()->put('impersonating_admin_id', (int) $adminId);
        $request->session()->put('impersonating_organization_id', (int) $organizationId);

        // Regenerate session to prevent session fixation
        $request->session()->regenerate();

        // Log the impersonation start
        AuditService::log(
            action: 'impersonation.started',
            organizationId: (int) $organizationId,
            resourceType: 'user',
            resourceId: (int) $userId,
            metadata: [
                'user_id' => (int) $userId,
                'organization_id' => (int) $organizationId,
            ],
        );

        return redirect()->route('dashboard');
    }

    public function stop(Request $request)
    {
        $organizationId = $request->session()->get('impersonating_organization_id');
        $userId = $request->session()->get('impersonating_user_id');

        // Log before clearing session
        AuditService::log(
            action: 'impersonation.stopped',
            organizationId: $organizationId,
            resourceType: 'user',
            resourceId: $userId,
            metadata: [
                'user_id' => $userId,
                'organization_id' => $organizationId,
            ],
        );

        // Clear session flags
        $request->session()->forget([
            'impersonating_user_id',
            'impersonating_admin_id',
            'impersonating_organization_id',
        ]);

        // Regenerate session to prevent session fixation
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
