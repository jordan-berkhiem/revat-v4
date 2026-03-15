<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignEmail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'raw_data_id',
        'integration_id',
        'effort_id',
        'external_id',
        'name',
        'subject',
        'from_name',
        'from_email',
        'type',
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
        'sent_at',
        'extraction_batch_id',
        'transformed_at',
    ];

    protected function casts(): array
    {
        return [
            'sent' => 'integer',
            'delivered' => 'integer',
            'bounced' => 'integer',
            'complaints' => 'integer',
            'unsubscribes' => 'integer',
            'opens' => 'integer',
            'unique_opens' => 'integer',
            'clicks' => 'integer',
            'unique_clicks' => 'integer',
            'platform_revenue' => 'decimal:2',
            'sent_at' => 'datetime',
            'transformed_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function effort(): BelongsTo
    {
        return $this->belongsTo(Effort::class);
    }

    public function emailClicks(): HasMany
    {
        return $this->hasMany(CampaignEmailClick::class);
    }
}
