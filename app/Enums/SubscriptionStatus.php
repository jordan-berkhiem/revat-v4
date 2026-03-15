<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case GracePeriod = 'grace_period';
    case Incomplete = 'incomplete';
    case IncompleteExpired = 'incomplete_expired';
    case Unpaid = 'unpaid';
    case Paused = 'paused';
    case Ended = 'ended';
    case None = 'none';
}
