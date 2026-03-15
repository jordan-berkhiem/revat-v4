<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SummaryConversionDaily extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'summary_conversion_daily';

    protected $primaryKey = ['workspace_id', 'summary_date'];

    protected $fillable = [
        'workspace_id',
        'summary_date',
        'conversions_count',
        'revenue',
        'payout',
        'cost',
        'summarized_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'revenue' => 'decimal:2',
            'payout' => 'decimal:2',
            'cost' => 'decimal:2',
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
}
