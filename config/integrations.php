<?php

use App\Http\Integrations\ActiveCampaign\ActiveCampaignConnector;
use App\Http\Integrations\ExpertSender\ExpertSenderConnector;
use App\Http\Integrations\Maropost\MaropostConnector;
use App\Http\Integrations\Voluum\VoluumConnector;

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Definitions
    |--------------------------------------------------------------------------
    |
    | Each platform defines its connector class, credential fields,
    | supported data types, and any platform-specific configuration.
    |
    */

    'platforms' => [
        'activecampaign' => [
            'connector' => ActiveCampaignConnector::class,
            'label' => 'ActiveCampaign',
            'data_types' => ['campaign_emails', 'campaign_email_clicks'],
            'credential_fields' => ['api_url', 'api_key'],
        ],
        'expertsender' => [
            'connector' => ExpertSenderConnector::class,
            'label' => 'ExpertSender',
            'data_types' => ['campaign_emails', 'campaign_email_clicks'],
            'credential_fields' => ['api_url', 'api_key'],
        ],
        'maropost' => [
            'connector' => MaropostConnector::class,
            'label' => 'Maropost',
            'data_types' => ['campaign_emails', 'campaign_email_clicks'],
            'credential_fields' => ['account_id', 'auth_token'],
        ],
        'voluum' => [
            'connector' => VoluumConnector::class,
            'label' => 'Voluum',
            'data_types' => ['conversion_sales'],
            'credential_fields' => ['access_key_id', 'access_key_secret'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Defaults
    |--------------------------------------------------------------------------
    */

    'http' => [
        'connect_timeout' => 10,
        'timeout' => 30,
        'max_response_size' => 50 * 1024 * 1024, // 50MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Orchestration
    |--------------------------------------------------------------------------
    */

    'max_concurrent_syncs' => (int) env('INTEGRATION_MAX_CONCURRENT_SYNCS', 3),
];
