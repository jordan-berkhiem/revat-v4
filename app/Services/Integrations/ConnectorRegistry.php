<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\PlatformConnector;
use App\Models\Integration;
use InvalidArgumentException;

class ConnectorRegistry
{
    /**
     * Resolve a PlatformConnector instance for the given integration.
     */
    public function resolve(Integration $integration): PlatformConnector
    {
        $config = $this->platformConfig($integration->platform);
        $connectorClass = $config['connector'];

        if (! class_exists($connectorClass)) {
            throw new InvalidArgumentException(
                "Connector class '{$connectorClass}' for platform '{$integration->platform}' does not exist."
            );
        }

        return new $connectorClass($integration);
    }

    /**
     * Return all registered platform slugs.
     */
    public function platforms(): array
    {
        return array_keys(config('integrations.platforms', []));
    }

    /**
     * Return config for a specific platform.
     */
    public function platformConfig(string $platform): array
    {
        $config = config("integrations.platforms.{$platform}");

        if (! $config) {
            throw new InvalidArgumentException(
                "Platform '{$platform}' is not registered in config/integrations.php."
            );
        }

        return $config;
    }
}
