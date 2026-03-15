<?php

namespace App\Services\Transformation;

use Illuminate\Database\Eloquent\Model;

class ChangeDetector
{
    /**
     * Check if a raw data record has changed since last processing.
     * Returns true if the record is new (null hash) or has changed (hash mismatch).
     */
    public function hasChanged(Model $rawRecord): bool
    {
        $currentHash = $rawRecord->content_hash;

        if ($currentHash === null) {
            return true;
        }

        $computedHash = $this->computeHash($rawRecord->raw_data);

        return $currentHash !== $computedHash;
    }

    /**
     * Compute a deterministic SHA-256 hash of the payload.
     * Keys are recursively sorted to ensure determinism.
     */
    public function computeHash(array $payload): string
    {
        $this->recursiveKsort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Mark a raw data record as processed by setting its content_hash.
     */
    public function markProcessed(Model $rawRecord): void
    {
        $hash = $this->computeHash($rawRecord->raw_data);
        $rawRecord->content_hash = $hash;
        $rawRecord->save();
    }

    /**
     * Recursively sort array keys for deterministic JSON encoding.
     */
    protected function recursiveKsort(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveKsort($value);
            }
        }
    }
}
