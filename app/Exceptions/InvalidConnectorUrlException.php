<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidConnectorUrlException extends RuntimeException
{
    public static function notHttps(string $url): self
    {
        return new self("Connector URL must use HTTPS: '{$url}'.");
    }

    public static function privateIp(string $url): self
    {
        return new self("Connector URL resolves to a private/reserved IP address: '{$url}'.");
    }

    public static function invalidHost(string $url): self
    {
        return new self("Connector URL has an invalid host: '{$url}'.");
    }

    public static function nonStandardPort(string $url): self
    {
        return new self("Connector URL uses a non-standard port: '{$url}'.");
    }
}
