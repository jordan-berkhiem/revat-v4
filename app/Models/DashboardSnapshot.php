<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'dashboard_id',
        'created_by',
        'layout',
        'widget_count',
        'created_at',
    ];

    protected $casts = [
        'layout' => 'array',
        'widget_count' => 'integer',
        'created_at' => 'datetime',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
