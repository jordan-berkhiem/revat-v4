<?php

namespace App\Models\Archives;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveCampaignEmail extends Model
{
    const UPDATED_AT = null;

    const CREATED_AT = 'archived_at';

    protected $table = 'archives_campaign_emails';

    protected $fillable = [
        'workspace_id',
        'raw_data_id',
        'extraction_batch_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
