<?php

namespace App\Services\Transformation;

use App\Contracts\Transformation\Transformer;
use App\Exceptions\UnsupportedDataTypeException;

class TransformerRegistry
{
    /**
     * @var array<string, Transformer>
     */
    protected array $transformers = [];

    /**
     * Register a transformer for a data type.
     */
    public function register(string $dataType, Transformer $transformer): void
    {
        $this->transformers[$dataType] = $transformer;
    }

    /**
     * Resolve the transformer for a given data type.
     *
     * @throws UnsupportedDataTypeException
     */
    public function resolve(string $dataType): Transformer
    {
        if (! isset($this->transformers[$dataType])) {
            throw new UnsupportedDataTypeException("No transformer registered for data type '{$dataType}'.");
        }

        return $this->transformers[$dataType];
    }

    /**
     * Check if a transformer is registered for the given data type.
     */
    public function has(string $dataType): bool
    {
        return isset($this->transformers[$dataType]);
    }

    /**
     * Get all registered data types.
     *
     * @return array<string>
     */
    public function supportedTypes(): array
    {
        return array_keys($this->transformers);
    }
}
