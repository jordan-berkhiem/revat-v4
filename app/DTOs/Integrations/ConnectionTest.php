<?php

namespace App\DTOs\Integrations;

class ConnectionTest
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $accountName = null,
        public readonly array $details = [],
    ) {}

    public static function ok(string $message = 'Connection successful.', ?string $accountName = null, array $details = []): self
    {
        return new self(
            success: true,
            message: $message,
            accountName: $accountName,
            details: $details,
        );
    }

    public static function fail(string $message = 'Connection failed.', array $details = []): self
    {
        return new self(
            success: false,
            message: $message,
            details: $details,
        );
    }
}
