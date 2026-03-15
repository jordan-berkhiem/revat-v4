<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Events\OrganizationWorkspacesCascadedSoftDelete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Billable;

class Organization extends Model
{
    use Billable, SoftDeletes;

    protected $fillable = ['name', 'timezone'];

    protected $attributes = [
        'support_access_enabled' => false,
    ];

    protected $casts = [
        'support_access_enabled' => 'boolean',
        'require_2fa' => 'boolean',
    ];

    protected $hidden = [
        'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot('last_workspace_id')
            ->withTimestamps();
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function defaultWorkspace(): HasOne
    {
        return $this->hasOne(Workspace::class)->where('is_default', true);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    // ── Billing Helpers ────────────────────────────────────────────

    public function isOnFreePlan(): bool
    {
        return $this->plan_id === null || $this->plan?->slug === 'free';
    }

    public function isSubscribed(): bool
    {
        return $this->subscribed('default');
    }

    public function subscriptionStatus(): SubscriptionStatus
    {
        $subscription = $this->subscription('default');

        if (! $subscription) {
            return SubscriptionStatus::None;
        }

        if ($subscription->onGracePeriod()) {
            return SubscriptionStatus::GracePeriod;
        }

        if ($subscription->ended()) {
            return SubscriptionStatus::Ended;
        }

        if ($subscription->onTrial()) {
            return SubscriptionStatus::Trialing;
        }

        if ($subscription->pastDue()) {
            return SubscriptionStatus::PastDue;
        }

        if ($subscription->hasIncompletePayment()) {
            return SubscriptionStatus::Incomplete;
        }

        $stripeStatus = $subscription->stripe_status;

        return match ($stripeStatus) {
            'active' => SubscriptionStatus::Active,
            'trialing' => SubscriptionStatus::Trialing,
            'past_due' => SubscriptionStatus::PastDue,
            'canceled' => SubscriptionStatus::Canceled,
            'incomplete' => SubscriptionStatus::Incomplete,
            'incomplete_expired' => SubscriptionStatus::IncompleteExpired,
            'unpaid' => SubscriptionStatus::Unpaid,
            'paused' => SubscriptionStatus::Paused,
            default => SubscriptionStatus::None,
        };
    }

    // ── Support Access ───────────────────────────────────────────────

    public function toggleSupportAccess(bool $enabled): void
    {
        $this->support_access_enabled = $enabled;
        $this->save();
    }

    protected static function booted(): void
    {
        static::deleting(function (Organization $org) {
            if ($org->isForceDeleting()) {
                return;
            }

            DB::transaction(function () use ($org) {
                $org->workspaces()->each(function ($workspace) {
                    $workspace->delete(); // soft-delete
                });
            });

            event(new OrganizationWorkspacesCascadedSoftDelete($org));
        });

        static::restoring(function (Organization $org) {
            // Restore workspaces that were soft-deleted at the same time or after the org
            Workspace::withTrashed()
                ->where('organization_id', $org->id)
                ->where('deleted_at', '>=', $org->deleted_at)
                ->each(function ($workspace) {
                    $workspace->restore();
                });
        });
    }
}
