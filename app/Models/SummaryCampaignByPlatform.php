<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SummaryCampaignByPlatform extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'summary_campaign_by_platform';

    protected $primaryKey = ['workspace_id', 'platform', 'summary_date'];

    protected $fillable = [
        'workspace_id',
        'platform',
        'summary_date',
        'campaigns_count',
        'sent',
        'delivered',
        'bounced',
        'complaints',
        'unsubscribes',
        'opens',
        'unique_opens',
        'clicks',
        'unique_clicks',
        'platform_revenue',
        'summarized_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'platform_revenue' => 'decimal:2',
            'summarized_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeForDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('summary_date', [$start->toDateString(), $end->toDateString()]);
    }

    public function scopeForWorkspace($query, int $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }
}
