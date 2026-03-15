<?php

namespace App\Contracts\Transformation;

use App\Models\ExtractionBatch;
use App\Services\Transformation\TransformResult;

interface Transformer
{
    /**
     * Transform raw data records for the given extraction batch into normalized fact records.
     */
    public function transform(ExtractionBatch $batch): TransformResult;

    /**
     * Check whether this transformer supports the given data type.
     */
    public function supports(string $dataType): bool;
}
