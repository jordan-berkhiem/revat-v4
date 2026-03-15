<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'stripe_price_monthly',
        'stripe_price_yearly',
        'max_workspaces',
        'max_integrations_per_workspace',
        'max_users',
        'is_visible',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'max_workspaces' => 'integer',
            'max_integrations_per_workspace' => 'integer',
            'max_users' => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true)->orderBy('sort_order');
    }

    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }
}
