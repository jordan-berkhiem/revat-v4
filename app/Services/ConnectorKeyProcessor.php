<?php

namespace App\Services;

use App\Models\AttributionConnector;
use App\Models\AttributionKey;
use App\Models\CampaignEmail;
use App\Models\CampaignEmailClick;
use App\Models\ConversionSale;
use Illuminate\Support\Facades\DB;

class ConnectorKeyProcessor
{
    /**
     * Process keys for a connector: extract field values from campaign and conversion
     * records, hash them, and create attribution_keys + attribution_record_keys.
     */
    public function processKeys(AttributionConnector $connector): void
    {
        $mappings = $connector->field_mappings;

        foreach ($mappings as $mapping) {
            $campaignField = $mapping['campaign'];
            $conversionField = $mapping['conversion'];

            $this->processCampaignRecords($connector, $campaignField);
            $this->processCampaignClickRecords($connector, $campaignField);
            $this->processConversionRecords($connector, $conversionField);
        }
    }

    /**
     * Extract keys from campaign_emails and link them via attribution_record_keys.
     */
    protected function processCampaignRecords(AttributionConnector $connector, string $field): void
    {
        CampaignEmail::where('workspace_id', $connector->workspace_id)
            ->whereNotNull($field)
            ->chunkById(500, function ($records) use ($connector, $field) {
                $this->processRecordBatch($connector, $records, $field, 'campaign_email');
            });
    }

    /**
     * Extract keys from campaign_email_clicks linked to campaign_emails.
     * Uses the campaign_email's field value for the key.
     */
    protected function processCampaignClickRecords(AttributionConnector $connector, string $field): void
    {
        CampaignEmailClick::where('campaign_email_clicks.workspace_id', $connector->workspace_id)
            ->join('campaign_emails', 'campaign_email_clicks.campaign_email_id', '=', 'campaign_emails.id')
            ->whereNotNull("campaign_emails.{$field}")
            ->select('campaign_email_clicks.*', "campaign_emails.{$field} as _key_value")
            ->orderBy('campaign_email_clicks.id')
            ->chunk(500, function ($clicks) use ($connector) {
                foreach ($clicks as $click) {
                    $value = $click->_key_value;
                    $hexHash = hash('sha256', $value);

                    $key = $this->findOrCreateKey($connector, $hexHash, $value);

                    DB::table('attribution_record_keys')->updateOrInsert(
                        [
                            'connector_id' => $connector->id,
                            'record_type' => 'campaign_email_click',
                            'record_id' => $click->id,
                        ],
                        [
                            'attribution_key_id' => $key->id,
                            'workspace_id' => $connector->workspace_id,
                        ]
                    );
                }
            });
    }

    /**
     * Extract keys from conversion_sales and link them via attribution_record_keys.
     */
    protected function processConversionRecords(AttributionConnector $connector, string $field): void
    {
        ConversionSale::where('workspace_id', $connector->workspace_id)
            ->whereNotNull($field)
            ->chunkById(500, function ($records) use ($connector, $field) {
                $this->processRecordBatch($connector, $records, $field, 'conversion_sale');
            });
    }

    /**
     * Process a batch of records: extract field values, hash, upsert keys and record keys.
     */
    protected function processRecordBatch(
        AttributionConnector $connector,
        $records,
        string $field,
        string $recordType
    ): void {
        foreach ($records as $record) {
            $value = $record->{$field};
            if ($value === null || $value === '') {
                continue;
            }

            $hexHash = hash('sha256', $value);

            $key = $this->findOrCreateKey($connector, $hexHash, $value);

            DB::table('attribution_record_keys')->updateOrInsert(
                [
                    'connector_id' => $connector->id,
                    'record_type' => $recordType,
                    'record_id' => $record->id,
                ],
                [
                    'attribution_key_id' => $key->id,
                    'workspace_id' => $connector->workspace_id,
                ]
            );
        }
    }

    /**
     * Find or create an attribution key.
     * Uses raw binary comparison for reliable cross-DB support.
     */
    protected function findOrCreateKey(AttributionConnector $connector, string $hexHash, string $value): AttributionKey
    {
        $binaryHash = hex2bin($hexHash);

        $key = AttributionKey::where('workspace_id', $connector->workspace_id)
            ->where('connector_id', $connector->id)
            ->whereRaw('key_hash = ?', [$binaryHash])
            ->first();

        if (! $key) {
            $key = new AttributionKey;
            $key->workspace_id = $connector->workspace_id;
            $key->connector_id = $connector->id;
            $key->key_hash = $hexHash; // BinaryHash cast converts hex → binary
            $key->key_value = $value;
            $key->save();
        }

        return $key;
    }
}
