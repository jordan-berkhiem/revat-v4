<?php

namespace App\Services;

use App\Models\AttributionConnector;
use App\Models\AttributionKey;
use App\Models\Integration;
use App\Services\Integrations\ConnectorRegistry;
use Illuminate\Support\Facades\DB;

class ConnectorKeyProcessor
{
    public function __construct(
        protected ConnectorRegistry $registry,
    ) {}

    /**
     * Process keys for a connector: extract field values from raw_data JSON,
     * hash them, and create attribution_keys + attribution_record_keys.
     */
    public function processKeys(AttributionConnector $connector): void
    {
        $mappings = $connector->field_mappings;

        $campaignIntegration = $connector->campaign_integration_id
            ? Integration::find($connector->campaign_integration_id)
            : null;

        $conversionIntegration = $connector->conversion_integration_id
            ? Integration::find($connector->conversion_integration_id)
            : null;

        foreach ($mappings as $mapping) {
            $campaignField = $mapping['campaign'];
            $conversionField = $mapping['conversion'];

            if ($campaignIntegration) {
                $this->validateFieldName($campaignField, $campaignIntegration, $connector->campaign_data_type);
                $this->processCampaignRecords($connector, $campaignField);
                $this->processCampaignClickRecords($connector, $campaignField);
            }

            if ($conversionIntegration) {
                $this->validateFieldName($conversionField, $conversionIntegration, $connector->conversion_data_type);
                $this->processConversionRecords($connector, $conversionField, $conversionIntegration);
            }
        }
    }

    /**
     * Validate a field name against the platform's matchable fields whitelist.
     * Prevents SQL injection via JSON path interpolation.
     */
    protected function validateFieldName(string $field, Integration $integration, ?string $dataType): void
    {
        $connector = $this->registry->resolve($integration);
        $matchableFields = $connector->getMatchableFields($integration);

        $allowedValues = [];
        if ($dataType && isset($matchableFields[$dataType])) {
            $allowedValues = array_column($matchableFields[$dataType], 'value');
        } else {
            // Collect all values across data types
            foreach ($matchableFields as $fields) {
                $allowedValues = array_merge($allowedValues, array_column($fields, 'value'));
            }
        }

        if (! in_array($field, $allowedValues, true)) {
            throw new \InvalidArgumentException(
                "Field '{$field}' is not a valid matchable field for platform '{$integration->platform}'."
            );
        }
    }

    /**
     * Extract keys from campaign_emails via raw_data JSON.
     */
    protected function processCampaignRecords(AttributionConnector $connector, string $field): void
    {
        $jsonPath = '$.' . $field;

        DB::table('campaign_emails as ce')
            ->join('campaign_email_raw_data as cerd', 'cerd.id', '=', 'ce.raw_data_id')
            ->where('ce.workspace_id', $connector->workspace_id)
            ->whereNull('ce.deleted_at')
            ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cerd.raw_data, '{$jsonPath}'))"))
            ->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cerd.raw_data, '{$jsonPath}'))"), '!=', '')
            ->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cerd.raw_data, '{$jsonPath}'))"), '!=', 'null')
            ->select([
                'ce.id as record_id',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cerd.raw_data, '{$jsonPath}')) as field_value"),
            ])
            ->orderBy('ce.id')
            ->chunk(500, function ($rows) use ($connector) {
                $this->processRawDataBatch($connector, $rows, 'campaign_email');
            });
    }

    /**
     * Extract keys from campaign_email_clicks via the parent campaign_email's raw_data.
     */
    protected function processCampaignClickRecords(AttributionConnector $connector, string $field): void
    {
        $jsonPath = '$.' . $field;

        DB::table('campaign_email_clicks as cec')
            ->join('campaign_emails as ce', 'ce.id', '=', 'cec.campaign_email_id')
            ->join('campaign_email_raw_data as cerd', 'cerd.id', '=', 'ce.raw_data_id')
            ->where('cec.workspace_id', $connector->workspace_id)
            ->whereNull('cec.deleted_at')
            ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cerd.raw_data, '{$jsonPath}'))"))
            ->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cerd.raw_data, '{$jsonPath}'))"), '!=', '')
            ->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cerd.raw_data, '{$jsonPath}'))"), '!=', 'null')
            ->select([
                'cec.id as record_id',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(cerd.raw_data, '{$jsonPath}')) as field_value"),
            ])
            ->orderBy('cec.id')
            ->chunk(500, function ($rows) use ($connector) {
                $this->processRawDataBatch($connector, $rows, 'campaign_email_click');
            });
    }

    /**
     * Extract keys from conversion_sales via raw_data JSON.
     * Voluum uses -TS resolution; other platforms use direct JSON_EXTRACT.
     */
    protected function processConversionRecords(
        AttributionConnector $connector,
        string $field,
        Integration $integration,
    ): void {
        if ($integration->platform === 'voluum') {
            $this->processVoluumConversionRecords($connector, $field);
        } else {
            $this->processDirectConversionRecords($connector, $field);
        }
    }

    /**
     * Voluum: resolve friendly name to customVariableN value via -TS CASE expression.
     */
    protected function processVoluumConversionRecords(AttributionConnector $connector, string $friendlyName): void
    {
        $caseExpression = $this->buildVoluumCaseExpression($friendlyName);

        DB::table('conversion_sales as cs')
            ->join('conversion_sale_raw_data as csrd', 'csrd.id', '=', 'cs.raw_data_id')
            ->where('cs.workspace_id', $connector->workspace_id)
            ->whereNull('cs.deleted_at')
            ->select([
                'cs.id as record_id',
                DB::raw("{$caseExpression} as field_value"),
            ])
            ->orderBy('cs.id')
            ->chunk(500, function ($rows) use ($connector) {
                $this->processRawDataBatch($connector, $rows, 'conversion_sale');
            });
    }

    /**
     * Non-Voluum conversions: direct JSON_EXTRACT on raw_data.
     */
    protected function processDirectConversionRecords(AttributionConnector $connector, string $field): void
    {
        $jsonPath = '$.' . $field;

        DB::table('conversion_sales as cs')
            ->join('conversion_sale_raw_data as csrd', 'csrd.id', '=', 'cs.raw_data_id')
            ->where('cs.workspace_id', $connector->workspace_id)
            ->whereNull('cs.deleted_at')
            ->whereNotNull(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(csrd.raw_data, '{$jsonPath}'))"))
            ->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(csrd.raw_data, '{$jsonPath}'))"), '!=', '')
            ->where(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(csrd.raw_data, '{$jsonPath}'))"), '!=', 'null')
            ->select([
                'cs.id as record_id',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(csrd.raw_data, '{$jsonPath}')) as field_value"),
            ])
            ->orderBy('cs.id')
            ->chunk(500, function ($rows) use ($connector) {
                $this->processRawDataBatch($connector, $rows, 'conversion_sale');
            });
    }

    /**
     * Build a CASE expression that resolves a Voluum friendly name to the
     * correct customVariableN value by matching against -TS fields.
     */
    protected function buildVoluumCaseExpression(string $friendlyName): string
    {
        // The friendly name is already validated against getMatchableFields()
        $escaped = addslashes($friendlyName);

        $branches = [];
        for ($n = 1; $n <= 10; $n++) {
            $branches[] = "WHEN JSON_UNQUOTE(JSON_EXTRACT(csrd.raw_data, '$.\"customVariable{$n}-TS\"')) = '{$escaped}'"
                . " THEN JSON_UNQUOTE(JSON_EXTRACT(csrd.raw_data, '$.customVariable{$n}'))";
        }

        return 'CASE ' . implode(' ', $branches) . ' ELSE NULL END';
    }

    /**
     * Process a batch of raw_data query results: hash values and upsert keys.
     */
    protected function processRawDataBatch($connector, $rows, string $recordType): void
    {
        foreach ($rows as $row) {
            $value = $row->field_value;
            if ($value === null || $value === '') {
                continue;
            }

            $hexHash = hash('sha256', $value);
            $key = $this->findOrCreateKey($connector, $hexHash, $value);

            DB::table('attribution_record_keys')->updateOrInsert(
                [
                    'connector_id' => $connector->id,
                    'record_type' => $recordType,
                    'record_id' => $row->record_id,
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
