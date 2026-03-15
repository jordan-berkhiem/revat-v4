<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractionRecord extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'extraction_batch_id',
        'external_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    // ── Relationships ───────────────────────────────────────────────────

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ExtractionBatch::class, 'extraction_batch_id');
    }
}
