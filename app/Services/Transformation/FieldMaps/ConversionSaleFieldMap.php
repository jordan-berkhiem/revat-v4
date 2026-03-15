<?php

namespace App\Services\Transformation\FieldMaps;

class ConversionSaleFieldMap
{
    /**
     * Per-platform mappings from raw JSON field names to normalized fact columns.
     *
     * @var array<string, array<string, string>>
     */
    protected static array $maps = [
        'voluum' => [
            'conversionId' => 'external_id',
            'revenue' => 'revenue',
            'cost' => 'cost',
            'payout' => 'payout',
            'conversionTimestamp' => 'converted_at',
            'email' => '_subscriber_email',
        ],
    ];

    /**
     * Default/fallback mapping for unknown platforms.
     *
     * @var array<string, string>
     */
    protected static array $default = [
        'id' => 'external_id',
        'revenue' => 'revenue',
        'cost' => 'cost',
        'payout' => 'payout',
        'converted_at' => 'converted_at',
        'email' => '_subscriber_email',
    ];

    /**
     * Get the field map for a given platform.
     *
     * @return array<string, string>
     */
    public static function for(string $platform): array
    {
        return static::$maps[strtolower($platform)] ?? static::$default;
    }

    /**
     * Map a raw data payload to normalized fact columns using the platform's field map.
     *
     * @return array<string, mixed>
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

        return $mapped;
    }
}
