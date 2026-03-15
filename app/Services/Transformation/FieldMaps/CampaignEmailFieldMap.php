<?php

namespace App\Services\Transformation\FieldMaps;

use RuntimeException;

class CampaignEmailFieldMap
{
    /**
     * Per-platform mappings from raw API field names to fact columns.
     *
     * Connectors store the full API response, so these maps reflect the
     * original API field names as they appear in raw_data. Computed/enrichment
     * fields added by the connector are also mapped here.
     *
     * @var array<string, array<string, string>>
     */
    protected static array $maps = [
        'activecampaign' => [
            'external_id' => 'external_id',
            'name' => 'name',
            'subject' => 'subject',
            'fromname' => 'from_name',
            'fromemail' => 'from_email',
            'send_amt' => 'sent',
            'delivered' => 'delivered',
            '_bounces' => 'bounced',
            'unsubscribes' => 'unsubscribes',
            'opens' => 'opens',
            'uniqueopens' => 'unique_opens',
            'linkclicks' => 'clicks',
            'uniquelinkclicks' => 'unique_clicks',
            'sdate' => 'sent_at',
        ],
        'expertsender' => [
            'external_id' => 'external_id',
            'Tags' => 'name',
            'Subject' => 'subject',
            'FromName' => 'from_name',
            'FromEmail' => 'from_email',
            'Sent' => 'sent',
            'Delivered' => 'delivered',
            'Bounced' => 'bounced',
            'Unsubscribes' => 'unsubscribes',
            'Opens' => 'opens',
            'UniqueOpens' => 'unique_opens',
            'Clicks' => 'clicks',
            'UniqueClicks' => 'unique_clicks',
            'SentDate' => 'sent_at',
        ],
        'maropost' => [
            'external_id' => 'external_id',
            'name' => 'name',
            'subject' => 'subject',
            'from_name' => 'from_name',
            'from_email' => 'from_email',
            'total_sent' => 'sent',
            'total_opens' => 'opens',
            'total_unique_opens' => 'unique_opens',
            'total_clicks' => 'clicks',
            'total_unique_clicks' => 'unique_clicks',
            'total_unsubscribes' => 'unsubscribes',
            'total_bounces' => 'bounced',
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
