<?php

namespace App\Exceptions;

use RuntimeException;

class UnsupportedDataTypeException extends RuntimeException
{
    public static function forPlatform(string $platform, string $dataType): self
    {
        return new self("Platform '{$platform}' does not support data type '{$dataType}'.");
    }
}
