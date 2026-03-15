<?php

namespace App\Http\Middleware;

use App\Services\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspace
{
    public function __construct(
        protected WorkspaceContext $workspaceContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Defensive check: EnsureOrganization must have run first
        if (! $user->current_organization_id) {
            throw new \RuntimeException(
                'EnsureWorkspace middleware requires EnsureOrganization to run first.'
            );
        }

        $organization = $user->currentOrganization;

        // Check if there's a cached workspace and validate it
        $cachedWorkspace = $this->workspaceContext->getWorkspace();
        if ($cachedWorkspace) {
            $accessibleIds = $this->workspaceContext->accessibleWorkspaceIds($user, $organization);
            if ($accessibleIds->contains($cachedWorkspace->id)) {
                return $next($request);
            }
            // Cached workspace is no longer accessible — clear it
            $this->workspaceContext->clearWorkspace();
        }

        // Resolve workspace
        $workspace = $this->workspaceContext->resolveWorkspace($user, $organization);

        if (! $workspace) {
            $accessibleIds = $this->workspaceContext->accessibleWorkspaceIds($user, $organization);
            if ($accessibleIds->isEmpty()) {
                if ($request->routeIs('workspace.none')) {
                    return $next($request);
                }

                return redirect()->route('workspace.none');
            }
        }

        if ($workspace) {
            $this->workspaceContext->setWorkspace($workspace);
        }

        return $next($request);
    }
}
