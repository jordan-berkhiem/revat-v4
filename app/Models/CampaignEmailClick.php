<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignEmailClick extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'raw_data_id',
        'integration_id',
        'campaign_email_id',
        'identity_hash_id',
        'clicked_at',
        'extraction_batch_id',
        'transformed_at',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
            'transformed_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function campaignEmail(): BelongsTo
    {
        return $this->belongsTo(CampaignEmail::class);
    }
}
