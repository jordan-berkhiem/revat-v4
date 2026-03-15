<?php

namespace App\Services\Transformation;

class TransformResult
{
    /**
     * @param  array<int, array{raw_data_id: int, error: string}>  $errors
     */
    public function __construct(
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $skipped = 0,
        public readonly array $errors = [],
    ) {}

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
