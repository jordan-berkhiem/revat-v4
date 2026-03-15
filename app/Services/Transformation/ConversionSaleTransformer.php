<?php

namespace App\Services\Transformation;

use App\Contracts\Transformation\Transformer;
use App\Models\Archives\ArchiveConversionSale;
use App\Models\ConversionSale;
use App\Models\ConversionSaleRawData;
use App\Models\ExtractionBatch;
use App\Services\Transformation\FieldMaps\ConversionSaleFieldMap;
use Carbon\Carbon;

class ConversionSaleTransformer implements Transformer
{
    protected int $chunkSize = 500;

    protected bool $force = false;

    public function __construct(
        protected IdentityHashingService $identityHashingService,
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

        ConversionSaleRawData::where('integration_id', $batch->integration_id)
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
        return $dataType === 'conversion_sales';
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
        ConversionSaleRawData $rawRecord,
        ExtractionBatch $batch,
        string $platform,
        int &$created,
        int &$updated,
        int &$skipped,
    ): void {
        // Archive the raw data before transformation
        ArchiveConversionSale::create([
            'workspace_id' => $rawRecord->workspace_id,
            'raw_data_id' => $rawRecord->id,
            'extraction_batch_id' => $batch->id,
            'payload' => $rawRecord->raw_data,
        ]);

        // Map raw fields to normalized columns
        $mapped = ConversionSaleFieldMap::map($rawRecord->raw_data, $platform);

        if (empty($mapped['external_id'])) {
            $mapped['external_id'] = $rawRecord->external_id;
        }

        // Resolve identity hash from subscriber email
        $subscriberEmail = $mapped['_subscriber_email'] ?? null;
        unset($mapped['_subscriber_email']);

        $identityHashId = null;
        if ($subscriberEmail) {
            $identityHash = $this->identityHashingService->resolveOrCreate(
                $rawRecord->workspace_id,
                $subscriberEmail,
            );
            $identityHashId = $identityHash?->id;
        }

        // Normalize monetary fields
        foreach (['revenue', 'payout', 'cost'] as $field) {
            if (array_key_exists($field, $mapped) && $mapped[$field] !== null) {
                $mapped[$field] = $this->parseMonetary($mapped[$field]);
            }
        }

        // Parse converted_at timestamp
        if (isset($mapped['converted_at'])) {
            $mapped['converted_at'] = $this->parseTimestamp($mapped['converted_at']);
        }

        // Add ETL metadata
        $mapped['workspace_id'] = $rawRecord->workspace_id;
        $mapped['raw_data_id'] = $rawRecord->id;
        $mapped['integration_id'] = $batch->integration_id;
        $mapped['identity_hash_id'] = $identityHashId;
        $mapped['extraction_batch_id'] = $batch->id;
        $mapped['transformed_at'] = now();

        // Upsert using raw_data_id as the unique key
        $existing = ConversionSale::where('raw_data_id', $rawRecord->id)->first();

        if ($existing) {
            $existing->update($mapped);
            $updated++;
        } else {
            ConversionSale::create($mapped);
            $created++;
        }
    }

    /**
     * Parse monetary values from various formats to float.
     * Handles: "$12.50", "12.50", numeric values, currency symbols.
     */
    protected function parseMonetary(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        // Strip currency symbols and whitespace
        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);

        if ($cleaned === '' || $cleaned === null) {
            return null;
        }

        return round((float) $cleaned, 2);
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

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
