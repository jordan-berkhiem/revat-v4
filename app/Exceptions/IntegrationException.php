<?php

namespace App\Exceptions;

use RuntimeException;

class IntegrationException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $platform = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
