<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchWorkspaceController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();
        $currentOrg = $user->currentOrganization;

        // Verify workspace belongs to the current organization
        if (! $currentOrg || $workspace->organization_id !== $currentOrg->id) {
            abort(403, 'This workspace does not belong to your current organization.');
        }

        // Verify user has access to the workspace
        if (! $user->accessibleWorkspaceIds($currentOrg)->contains($workspace->id)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Update workspace context
        app(WorkspaceContext::class)->setWorkspace($workspace);

        return redirect()->back();
    }
}
