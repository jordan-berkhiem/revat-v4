<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExtractionRecords extends Command
{
    protected $signature = 'extraction:prune {--ttl=24 : Hours after which orphaned records are pruned}';

    protected $description = 'Delete extraction records for completed batches and orphaned records past TTL';

    public function handle(): int
    {
        $ttlHours = (int) $this->option('ttl');
        $totalPruned = 0;

        // Delete records for completed/transformed batches
        $totalPruned += $this->pruneByStatus(['completed', 'transformed']);

        // Delete records older than TTL regardless of batch status (catches stuck/orphaned batches)
        $totalPruned += $this->pruneByAge($ttlHours);

        $this->info("Pruned {$totalPruned} extraction records.");

        return self::SUCCESS;
    }

    protected function pruneByStatus(array $statuses): int
    {
        $pruned = 0;

        do {
            $deleted = DB::table('extraction_records')
                ->whereIn('extraction_batch_id', function ($query) use ($statuses) {
                    $query->select('id')
                        ->from('extraction_batches')
                        ->whereIn('status', $statuses);
                })
                ->limit(10000)
                ->delete();

            $pruned += $deleted;
        } while ($deleted > 0);

        return $pruned;
    }

    protected function pruneByAge(int $hours): int
    {
        $pruned = 0;
        $cutoff = now()->subHours($hours);

        do {
            $deleted = DB::table('extraction_records')
                ->where('created_at', '<', $cutoff)
                ->limit(10000)
                ->delete();

            $pruned += $deleted;
        } while ($deleted > 0);

        return $pruned;
    }
}
