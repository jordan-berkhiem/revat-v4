<?php

namespace App\Jobs;

use App\Models\ExtractionBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TransformExtractionBatches implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    /**
     * Data type ordering: campaign_emails must be processed before clicks
     * so that campaign_email_id FK can be resolved.
     */
    protected const DATA_TYPE_ORDER = [
        'campaign_emails' => 1,
        'conversion_sales' => 2,
        'campaign_email_clicks' => 3,
    ];

    public function __construct()
    {
        $this->onQueue(config('queues.transformation'));
    }

    public function handle(): void
    {
        $limit = config('transformation.batch_limit', 50);

        $batches = ExtractionBatch::where('status', ExtractionBatch::STATUS_EXTRACTED)
            ->whereNull('transformed_at')
            ->orderByRaw($this->dataTypeOrderSql())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($batches->isEmpty()) {
            return;
        }

        Log::info('TransformExtractionBatches: Dispatching transformation jobs', [
            'batch_count' => $batches->count(),
        ]);

        foreach ($batches as $batch) {
            TransformBatch::dispatch($batch);
        }
    }

    /**
     * Build SQL CASE expression for data type ordering.
     */
    protected function dataTypeOrderSql(): string
    {
        $cases = [];
        foreach (self::DATA_TYPE_ORDER as $type => $order) {
            $cases[] = "WHEN data_type = '{$type}' THEN {$order}";
        }

        return 'CASE '.implode(' ', $cases).' ELSE 99 END';
    }
}
