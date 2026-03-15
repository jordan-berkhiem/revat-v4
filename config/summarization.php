<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Workspace Limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of workspaces to dispatch for summarization
    | in a single scheduler run.
    |
    */

    'workspace_limit' => (int) env('SUMMARIZATION_WORKSPACE_LIMIT', 20),

];
