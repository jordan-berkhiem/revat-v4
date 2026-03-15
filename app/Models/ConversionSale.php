<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversionSale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'raw_data_id',
        'integration_id',
        'external_id',
        'identity_hash_id',
        'revenue',
        'payout',
        'cost',
        'converted_at',
        'extraction_batch_id',
        'transformed_at',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'payout' => 'decimal:2',
            'cost' => 'decimal:2',
            'converted_at' => 'datetime',
            'transformed_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
