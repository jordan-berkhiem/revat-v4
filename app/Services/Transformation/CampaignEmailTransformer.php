<?php

namespace App\Services\Transformation;

use App\Contracts\Transformation\Transformer;
use App\Models\Archives\ArchiveCampaignEmail;
use App\Models\CampaignEmail;
use App\Models\CampaignEmailRawData;
use App\Models\ExtractionBatch;
use App\Services\Transformation\FieldMaps\CampaignEmailFieldMap;
use Carbon\Carbon;

class CampaignEmailTransformer implements Transformer
{
    protected int $chunkSize = 500;

    protected bool $force = false;

    public function __construct(
        protected ChangeDetector $changeDetector,
    ) {}

    public function transform(ExtractionBatch $batch): TransformResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $platform = $batch->integration->platform;
        $force = $this->force || $batch->force_transform;

        CampaignEmailRawData::where('integration_id', $batch->integration_id)
            ->where('workspace_id', $batch->workspace_id)
            ->chunkById($this->chunkSize, function ($rawRecords) use ($batch, $platform, $force, &$created, &$updated, &$skipped, &$errors) {
                foreach ($rawRecords as $rawRecord) {
                    try {
                        if (! $force && ! $this->changeDetector->hasChanged($rawRecord)) {
                            $skipped++;

                            continue;
                        }

                        $this->transformRecord($rawRecord, $batch, $platform, $created, $updated, $skipped);
                        $this->changeDetector->markProcessed($rawRecord);
                    } catch (\Throwable $e) {
                        $errors[] = [
                            'raw_data_id' => $rawRecord->id,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            });

        return new TransformResult($created, $updated, $skipped, $errors);
    }

    public function supports(string $dataType): bool
    {
        return $dataType === 'campaign_emails';
    }

    public function setChunkSize(int $size): static
    {
        $this->chunkSize = $size;

        return $this;
    }

    public function setForce(bool $force): static
    {
        $this->force = $force;

        return $this;
    }

    protected function transformRecord(
        CampaignEmailRawData $rawRecord,
        ExtractionBatch $batch,
        string $platform,
        int &$created,
        int &$updated,
        int &$skipped,
    ): void {
        // Archive the raw data before transformation
        ArchiveCampaignEmail::create([
            'workspace_id' => $rawRecord->workspace_id,
            'raw_data_id' => $rawRecord->id,
            'extraction_batch_id' => $batch->id,
            'payload' => $rawRecord->raw_data,
        ]);

        // Map raw fields to normalized columns
        $mapped = CampaignEmailFieldMap::map($rawRecord->raw_data, $platform);

        if (empty($mapped['external_id'])) {
            $mapped['external_id'] = $rawRecord->external_id;
        }

        // Parse sent_at timestamp
        if (isset($mapped['sent_at'])) {
            $mapped['sent_at'] = $this->parseTimestamp($mapped['sent_at']);
        }

        // Cast numeric metrics — preserve NULL for missing fields
        foreach (['sent', 'delivered', 'bounced', 'complaints', 'unsubscribes', 'opens', 'unique_opens', 'clicks', 'unique_clicks'] as $metric) {
            if (array_key_exists($metric, $mapped) && $mapped[$metric] !== null) {
                $mapped[$metric] = (int) $mapped[$metric];
            }
        }

        if (array_key_exists('platform_revenue', $mapped) && $mapped['platform_revenue'] !== null) {
            $mapped['platform_revenue'] = (float) $mapped['platform_revenue'];
        }

        // Add ETL metadata
        $mapped['workspace_id'] = $rawRecord->workspace_id;
        $mapped['raw_data_id'] = $rawRecord->id;
        $mapped['integration_id'] = $batch->integration_id;
        $mapped['extraction_batch_id'] = $batch->id;
        $mapped['transformed_at'] = now();

        // Upsert using raw_data_id as the unique key
        $existing = CampaignEmail::where('raw_data_id', $rawRecord->id)->first();

        if ($existing) {
            $existing->update($mapped);
            $updated++;
        } else {
            CampaignEmail::create($mapped);
            $created++;
        }
    }

    /**
     * Parse various timestamp formats into a Carbon instance.
     */
    protected function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        // Unix timestamp (numeric)
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        // String timestamp — try Carbon's flexible parsing
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
