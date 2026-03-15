<?php

namespace App\Services\Transformation;

use App\Contracts\Transformation\Transformer;
use App\Models\Archives\ArchiveCampaignEmailClick;
use App\Models\CampaignEmail;
use App\Models\CampaignEmailClick;
use App\Models\CampaignEmailClickRawData;
use App\Models\ExtractionBatch;
use Carbon\Carbon;

class CampaignEmailClickTransformer implements Transformer
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

        $force = $this->force || $batch->force_transform;

        CampaignEmailClickRawData::where('integration_id', $batch->integration_id)
            ->where('workspace_id', $batch->workspace_id)
            ->chunkById($this->chunkSize, function ($rawRecords) use ($batch, $force, &$created, &$updated, &$skipped, &$errors) {
                foreach ($rawRecords as $rawRecord) {
                    try {
                        if (! $force && ! $this->changeDetector->hasChanged($rawRecord)) {
                            $skipped++;

                            continue;
                        }

                        $this->transformRecord($rawRecord, $batch, $created, $updated, $skipped);
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
        return $dataType === 'campaign_email_clicks';
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
        CampaignEmailClickRawData $rawRecord,
        ExtractionBatch $batch,
        int &$created,
        int &$updated,
        int &$skipped,
    ): void {
        // Archive the raw data before transformation
        ArchiveCampaignEmailClick::create([
            'workspace_id' => $rawRecord->workspace_id,
            'raw_data_id' => $rawRecord->id,
            'extraction_batch_id' => $batch->id,
            'payload' => $rawRecord->raw_data,
        ]);

        // Resolve campaign_email_id by matching external_campaign_id
        $campaignEmailId = $this->resolveCampaignEmailId(
            $rawRecord->integration_id,
            $rawRecord->external_campaign_id,
        );

        // Resolve identity_hash_id from subscriber email
        $identityHashId = $this->resolveIdentityHashId(
            $rawRecord->workspace_id,
            $rawRecord->raw_data,
        );

        // Parse clicked_at from raw data
        $clickedAt = $this->parseClickedAt($rawRecord->raw_data);

        $attributes = [
            'workspace_id' => $rawRecord->workspace_id,
            'raw_data_id' => $rawRecord->id,
            'integration_id' => $batch->integration_id,
            'campaign_email_id' => $campaignEmailId,
            'identity_hash_id' => $identityHashId,
            'clicked_at' => $clickedAt ?? now(),
            'extraction_batch_id' => $batch->id,
            'transformed_at' => now(),
        ];

        // Upsert using raw_data_id as the unique key
        $existing = CampaignEmailClick::where('raw_data_id', $rawRecord->id)->first();

        if ($existing) {
            $existing->update($attributes);
            $updated++;
        } else {
            CampaignEmailClick::create($attributes);
            $created++;
        }
    }

    /**
     * Resolve campaign_email_id by looking up campaign_emails by integration_id + external_id.
     */
    protected function resolveCampaignEmailId(int $integrationId, string $externalCampaignId): ?int
    {
        $campaignEmail = CampaignEmail::where('integration_id', $integrationId)
            ->where('external_id', $externalCampaignId)
            ->first();

        return $campaignEmail?->id;
    }

    /**
     * Resolve identity_hash_id from the raw data payload.
     * Prefers original email if available, otherwise returns NULL.
     */
    protected function resolveIdentityHashId(int $workspaceId, array $rawData): ?int
    {
        // Prefer original email if available in the payload
        $email = $rawData['email'] ?? $rawData['subscriber_email'] ?? $rawData['Email'] ?? null;

        if ($email) {
            $identityHash = $this->identityHashingService->resolveOrCreate($workspaceId, $email);

            return $identityHash?->id;
        }

        return null;
    }

    /**
     * Parse clicked_at timestamp from raw data.
     */
    protected function parseClickedAt(array $rawData): ?Carbon
    {
        $value = $rawData['clicked_at'] ?? $rawData['ClickDate'] ?? $rawData['click_date'] ?? $rawData['timestamp'] ?? null;

        if ($value === null || $value === '') {
            return null;
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
