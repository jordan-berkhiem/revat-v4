<?php

namespace App\Enums;

enum SupportLevel: string
{
    case Agent = 'agent';
    case Manager = 'manager';
    case Super = 'super';

    /**
     * Check if this support level is at least the given level.
     *
     * Hierarchy: agent < manager < super
     */
    public function isAtLeast(SupportLevel $level): bool
    {
        return $this->ordinal() >= $level->ordinal();
    }

    private function ordinal(): int
    {
        return match ($this) {
            self::Agent => 0,
            self::Manager => 1,
            self::Super => 2,
        };
    }
}
