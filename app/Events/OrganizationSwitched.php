<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class OrganizationSwitched
{
    use Dispatchable;

    public function __construct(
        public readonly int $user_id,
        public readonly ?int $from_organization_id,
        public readonly int $to_organization_id,
        public readonly ?string $ip_address,
        public readonly \DateTimeInterface $occurred_at,
    ) {}
}
