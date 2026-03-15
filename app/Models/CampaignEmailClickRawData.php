<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignEmailClickRawData extends Model
{
    public $timestamps = false;

    protected $table = 'campaign_email_click_raw_data';

    protected $fillable = [
        'workspace_id',
        'integration_id',
        'external_campaign_id',
        'subscriber_email_hash',
        'clicked_url',
        'url_params',
        'raw_data',
        'content_hash',
    ];

    protected $casts = [
        'url_params' => 'array',
        'raw_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relationships ───────────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
