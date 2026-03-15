<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchOrganizationController extends Controller
{
    public function __invoke(Request $request, Organization $organization): RedirectResponse
    {
        $user = $request->user();

        if ($user->isBeingImpersonated()) {
            abort(403, 'Organization switching is not allowed during impersonation.');
        }

        // Verify user is a member of the target organization
        if (! $user->organizations()->where('organizations.id', $organization->id)->exists()) {
            abort(403, 'You are not a member of this organization.');
        }

        // Update current_organization_id on the User model
        $user->switchOrganization($organization);

        // Resolve and set the workspace context for the new organization
        $workspaceContext = app(WorkspaceContext::class);
        $workspaceContext->clearWorkspace();

        $workspace = $workspaceContext->resolveWorkspace($user, $organization);
        if ($workspace) {
            $workspaceContext->setWorkspace($workspace);
        }

        return redirect()->route('dashboard');
    }
}
