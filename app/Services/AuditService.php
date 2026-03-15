<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an audit entry.
     *
     * Auto-captures admin_id from admin guard, user_id from web guard,
     * and ip_address from the current request.
     *
     * Actor rule: exactly one of admin_id or user_id should be set per row (never both).
     * Both NULL for system-generated entries.
     */
    public static function log(
        string $action,
        ?int $organizationId = null,
        ?string $resourceType = null,
        ?int $resourceId = null,
        array $metadata = [],
    ): AuditLog {
        $adminId = null;
        $userId = null;

        $webUser = Auth::guard('web')->user();
        $adminUser = Auth::guard('admin')->user();

        // If the web user is being impersonated, the real actor is the admin
        if ($webUser && $webUser->isBeingImpersonated()) {
            $adminId = session('impersonating_admin_id');
        } elseif ($adminUser) {
            $adminId = $adminUser->id;
        } elseif ($webUser) {
            $userId = $webUser->id;
        }

        $auditLog = new AuditLog;
        $auditLog->forceFill([
            'admin_id' => $adminId,
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'ip_address' => Request::ip(),
        ]);
        $auditLog->fill([
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => ! empty($metadata) ? $metadata : null,
        ]);
        $auditLog->save();

        return $auditLog;
    }
}
