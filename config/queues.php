<?php

/*
|--------------------------------------------------------------------------
| Queue Name Registry
|--------------------------------------------------------------------------
|
| Single source of truth for all queue names used across the application.
| Every job class references this config instead of hardcoding queue names.
|
| Pipeline queues (extraction, transformation, attribution, summarization)
| are intentionally separated from the default queue so that Horizon can
| allocate workers independently. This prevents pipeline backpressure
| from blocking general application jobs like email notifications or
| billing webhooks.
|
| Queue names are short, lowercase, and descriptive to keep Horizon's
| UI readable and CLI commands ergonomic:
|   php artisan queue:work --queue=extraction
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue
    |--------------------------------------------------------------------------
    |
    | Low-priority, non-pipeline work: email notifications, billing webhooks,
    | usage aggregation, subscription checks, and other general tasks.
    |
    */
    'default' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Extraction Queue
    |--------------------------------------------------------------------------
    |
    | All extraction-related jobs: pulling data from platform connectors
    | (ActiveCampaign, ExpertSender, Maropost, Voluum), batching records,
    | and upserting raw data into staging tables.
    |
    */
    'extraction' => 'extraction',

    /*
    |--------------------------------------------------------------------------
    | Transformation Queue
    |--------------------------------------------------------------------------
    |
    | Transformation jobs that convert raw extraction data into normalized
    | domain models (campaign emails, clicks, conversion sales). Runs after
    | extraction batches complete.
    |
    */
    'transformation' => 'transformation',

    /*
    |--------------------------------------------------------------------------
    | Attribution Queue
    |--------------------------------------------------------------------------
    |
    | Attribution engine jobs that match conversions to campaigns using
    | configured attribution connectors and models (first-touch,
    | last-touch, linear). Runs after transformation completes.
    |
    */
    'attribution' => 'attribution',

    /*
    |--------------------------------------------------------------------------
    | Summarization Queue
    |--------------------------------------------------------------------------
    |
    | Summarization jobs that aggregate attribution results, campaign
    | performance, and conversion data into dashboard-ready summary
    | tables. The final stage of the data pipeline.
    |
    */
    'summarization' => 'summarization',

];
