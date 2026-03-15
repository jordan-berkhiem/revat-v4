<?php

namespace App\Services\Transformation\FieldMaps;

use RuntimeException;

class CampaignEmailFieldMap
{
    /**
     * Per-platform mappings from connector-normalized field names to fact columns.
     *
     * All connectors normalize their API responses to snake_case before storage,
     * so these maps reflect the connector output — NOT the raw API field names.
     *
     * @var array<string, array<string, string>>
     */
    protected static array $maps = [
        'activecampaign' => [
            'external_id' => 'external_id',
            'name' => 'name',
            'subject' => 'subject',
            'from_name' => 'from_name',
            'from_email' => 'from_email',
            'type' => 'type',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'bounces' => 'bounced',
            'complaints' => 'complaints',
            'unsubscribes' => 'unsubscribes',
            'opens' => 'opens',
            'unique_opens' => 'unique_opens',
            'clicks' => 'clicks',
            'unique_clicks' => 'unique_clicks',
            'platform_revenue' => 'platform_revenue',
            'sent_at' => 'sent_at',
        ],
        'expertsender' => [
            'external_id' => 'external_id',
            'name' => 'name',
            'subject' => 'subject',
            'from_name' => 'from_name',
            'from_email' => 'from_email',
            'type' => 'type',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'bounces' => 'bounced',
            'complaints' => 'complaints',
            'unsubscribes' => 'unsubscribes',
            'opens' => 'opens',
            'unique_opens' => 'unique_opens',
            'clicks' => 'clicks',
            'unique_clicks' => 'unique_clicks',
            'platform_revenue' => 'platform_revenue',
            'sent_at' => 'sent_at',
        ],
        'maropost' => [
            'external_id' => 'external_id',
            'name' => 'name',
            'subject' => 'subject',
            'from_name' => 'from_name',
            'from_email' => 'from_email',
            'type' => 'type',
            'sent' => 'sent',
            'delivered' => 'delivered',
            'bounces' => 'bounced',
            'complaints' => 'complaints',
            'unsubscribes' => 'unsubscribes',
            'opens' => 'opens',
            'unique_opens' => 'unique_opens',
            'clicks' => 'clicks',
            'unique_clicks' => 'unique_clicks',
            'platform_revenue' => 'platform_revenue',
            'sent_at' => 'sent_at',
        ],
    ];

    /**
     * Get the field map for a given platform.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException If no field map exists for the platform.
     */
    public static function for(string $platform): array
    {
        $key = strtolower($platform);

        if (! isset(static::$maps[$key])) {
            throw new RuntimeException("No CampaignEmail field map defined for platform [{$platform}]. Add one to CampaignEmailFieldMap::\$maps.");
        }

        return static::$maps[$key];
    }

    /**
     * Map a raw data payload to normalized fact columns using the platform's field map.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException If no field map exists for the platform or mapping produces no results.
     */
    public static function map(array $rawData, string $platform): array
    {
        $fieldMap = static::for($platform);
        $mapped = [];

        foreach ($fieldMap as $rawField => $factColumn) {
            if (array_key_exists($rawField, $rawData)) {
                $mapped[$factColumn] = $rawData[$rawField];
            }
        }

        if (empty($mapped)) {
            $expected = implode(', ', array_keys($fieldMap));
            $actual = implode(', ', array_keys($rawData));
            throw new RuntimeException(
                "CampaignEmail field mapping for platform [{$platform}] produced no results. "
                ."Expected keys: [{$expected}]. Actual keys: [{$actual}]."
            );
        }

        return $mapped;
    }
}
