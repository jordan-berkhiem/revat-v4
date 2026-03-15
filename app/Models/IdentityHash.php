<?php

namespace App\Models;

use App\Casts\BinaryHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityHash extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'workspace_id',
        'hash',
        'type',
        'hash_algorithm',
        'normalized_email_domain',
    ];

    protected function casts(): array
    {
        return [
            'hash' => BinaryHash::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
