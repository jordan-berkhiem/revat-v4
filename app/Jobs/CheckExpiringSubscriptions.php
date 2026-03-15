<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

class CheckExpiringSubscriptions implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var int[]
     */
    public array $backoff = [30, 60, 120];

    public function handle(): void
    {
        $threeDaysFromNow = now()->addDays(3)->endOfDay();
        $now = now();

        // Find subscriptions ending within 3 days (grace period ending)
        $subscriptions = DB::table('subscriptions')
            ->where('name', 'default')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [$now, $threeDaysFromNow])
            ->get();

        $notifiedCount = 0;

        foreach ($subscriptions as $subscription) {
            $organization = Organization::find($subscription->billable_id ?? null);
            if (! $organization) {
                continue;
            }

            // Find the organization owner
            app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
            $owner = $organization->users()
                ->role('owner')
                ->first();

            // Fallback: first user in organization
            if (! $owner) {
                $owner = $organization->users()->first();
            }

            if (! $owner) {
                continue;
            }

            // Check if we already sent a notification for this expiration
            $alreadySent = DB::table('notifications')
                ->where('notifiable_id', $owner->id)
                ->where('notifiable_type', get_class($owner))
                ->where('type', SubscriptionExpiringNotification::class)
                ->where('created_at', '>=', $now->copy()->subDays(3))
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $owner->notify(new SubscriptionExpiringNotification($organization));
            $notifiedCount++;
        }

        Log::info('CheckExpiringSubscriptions completed', ['notified' => $notifiedCount]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CheckExpiringSubscriptions failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
