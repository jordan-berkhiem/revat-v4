<?php

namespace App\Models;

use App\Casts\BinaryHash;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributionKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'connector_id',
        'key_hash',
        'key_value',
    ];

    protected function casts(): array
    {
        return [
            'key_hash' => BinaryHash::class,
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(AttributionConnector::class, 'connector_id');
    }

    public function recordKeys(): HasMany
    {
        return $this->hasMany(AttributionRecordKey::class, 'attribution_key_id');
    }
}
