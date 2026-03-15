<?php

namespace App\Exceptions;

class RateLimitException extends IntegrationException
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        int $code = 429,
        ?\Throwable $previous = null,
        ?string $platform = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message, $code, $previous, $platform);
    }
}
