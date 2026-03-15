<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batch Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of extraction batches to dispatch for transformation
    | in a single scheduler run. Prevents overwhelming the queue.
    |
    */

    'batch_limit' => (int) env('TRANSFORMATION_BATCH_LIMIT', 50),

];
