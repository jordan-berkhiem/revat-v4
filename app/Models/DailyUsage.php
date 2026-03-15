<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUsage extends Model
{
    protected $fillable = [
        'organization_id',
        'workspace_id',
        'recorded_on',
        'campaigns_synced',
        'conversions_synced',
        'active_integrations',
    ];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'campaigns_synced' => 'integer',
            'conversions_synced' => 'integer',
            'active_integrations' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeForDate($query, $date): void
    {
        $query->whereDate('recorded_on', $date);
    }

    public function scopeForOrganization($query, int $organizationId): void
    {
        $query->where('organization_id', $organizationId);
    }
}
