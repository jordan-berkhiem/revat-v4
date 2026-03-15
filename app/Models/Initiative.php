<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Initiative extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'program_id',
        'name',
        'code',
        'description',
        'status',
        'budget',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function efforts(): HasMany
    {
        return $this->hasMany(Effort::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForWorkspace(Builder $query, int|Workspace $workspace): Builder
    {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;

        return $query->where('workspace_id', $workspaceId);
    }

    // ── Boot ─────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::deleting(function (Initiative $initiative) {
            if ($initiative->isForceDeleting()) {
                return;
            }

            $initiative->efforts()->each(function (Effort $effort) {
                $effort->delete();
            });
        });

        static::restoring(function (Initiative $initiative) {
            Effort::withTrashed()
                ->where('initiative_id', $initiative->id)
                ->where('deleted_at', '>=', $initiative->deleted_at)
                ->each(function (Effort $effort) {
                    $effort->restore();
                });
        });
    }
}
