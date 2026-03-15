<?php

namespace App\Services;

use App\Models\AttributionConnector;
use App\Models\AttributionKey;
use App\Models\Effort;
use App\Models\Initiative;
use Illuminate\Database\QueryException;

class EffortResolver
{
    /**
     * Resolve efforts for all unresolved attribution keys on a connector.
     * Mapped connectors auto-create efforts with ak_{id} codes.
     * Simple connectors look up efforts by key_value as the effort code.
     */
    public function resolveEfforts(AttributionConnector $connector): void
    {
        $unresolvedKeys = AttributionKey::where('connector_id', $connector->id)
            ->whereNull('effort_id')
            ->get();

        if ($unresolvedKeys->isEmpty()) {
            return;
        }

        $defaultInitiative = $this->getDefaultInitiative($connector->workspace_id);

        if ($connector->type === 'simple') {
            $this->resolveSimpleConnector($unresolvedKeys, $connector, $defaultInitiative);
        } else {
            $this->resolveMappedConnector($unresolvedKeys, $connector, $defaultInitiative);
        }
    }

    protected function resolveMappedConnector($keys, AttributionConnector $connector, Initiative $defaultInitiative): void
    {
        foreach ($keys as $key) {
            $effort = $this->createEffort(
                workspaceId: $connector->workspace_id,
                initiativeId: $defaultInitiative->id,
                code: "ak_{$key->id}",
                name: "ak_{$key->id}",
                description: $key->key_value,
            );

            $key->effort_id = $effort->id;
            $key->save();
        }
    }

    protected function resolveSimpleConnector($keys, AttributionConnector $connector, Initiative $defaultInitiative): void
    {
        foreach ($keys as $key) {
            $effortCode = $key->key_value;

            if (mb_strlen($effortCode) > 50) {
                throw new \RuntimeException(
                    "EffortResolver: key_value '{$effortCode}' exceeds 50 chars for simple connector [{$connector->id}]. "
                    .'Fix the source data or field mapping.'
                );
            }

            $effort = Effort::where('workspace_id', $connector->workspace_id)
                ->where('code', $effortCode)
                ->first();

            if (! $effort) {
                $effort = $this->createEffort(
                    workspaceId: $connector->workspace_id,
                    initiativeId: $defaultInitiative->id,
                    code: $effortCode,
                    name: $effortCode,
                    description: $effortCode,
                );
            }

            $key->effort_id = $effort->id;
            $key->save();
        }
    }

    protected function createEffort(int $workspaceId, int $initiativeId, string $code, string $name, string $description): Effort
    {
        try {
            return Effort::create([
                'workspace_id' => $workspaceId,
                'initiative_id' => $initiativeId,
                'name' => $name,
                'code' => $code,
                'description' => $description,
                'auto_generated' => true,
                'channel_type' => 'email',
                'status' => 'active',
            ]);
        } catch (QueryException $e) {
            // Race condition: another process created the effort
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                return Effort::where('workspace_id', $workspaceId)
                    ->where('code', $code)
                    ->firstOrFail();
            }

            throw $e;
        }
    }

    protected function getDefaultInitiative(int $workspaceId): Initiative
    {
        $initiative = Initiative::where('workspace_id', $workspaceId)
            ->default()
            ->first();

        if (! $initiative) {
            throw new \RuntimeException(
                "EffortResolver: No default Initiative found for workspace [{$workspaceId}]. "
                .'Ensure WorkspaceObserver creates a default Initiative on workspace creation.'
            );
        }

        return $initiative;
    }
}
