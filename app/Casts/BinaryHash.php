<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class BinaryHash implements CastsAttributes
{
    /**
     * Convert raw binary to hex for PHP-side usage.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return bin2hex($value);
    }

    /**
     * Convert hex to binary for storage.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        // If already binary (32 bytes), store as-is
        if (strlen($value) === 32 && ! ctype_xdigit($value)) {
            return $value;
        }

        // If hex string, convert to binary
        if (ctype_xdigit($value)) {
            return hex2bin($value);
        }

        return $value;
    }
}
