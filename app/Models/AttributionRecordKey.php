<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributionRecordKey extends Model
{
    const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = [
        'connector_id',
        'attribution_key_id',
        'record_type',
        'record_id',
        'workspace_id',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function connector(): BelongsTo
    {
        return $this->belongsTo(AttributionConnector::class, 'connector_id');
    }

    public function attributionKey(): BelongsTo
    {
        return $this->belongsTo(AttributionKey::class, 'attribution_key_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeForRecord($query, string $type, int $id): void
    {
        $query->where('record_type', $type)->where('record_id', $id);
    }

    /**
     * Override to handle composite primary key for save queries.
     */
    protected function setKeysForSaveQuery($query)
    {
        $query->where('connector_id', $this->getAttribute('connector_id'))
            ->where('record_type', $this->getAttribute('record_type'))
            ->where('record_id', $this->getAttribute('record_id'));

        return $query;
    }
}
