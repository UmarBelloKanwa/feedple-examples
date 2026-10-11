<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feedple API Key
    |--------------------------------------------------------------------------
    |
    | The API key associated with your Feedple workspace (fdpl_... or sk_...).
    |
    */
    'api_key' => env('FEEDPLE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Feedple Webhook Signing Secret
    |--------------------------------------------------------------------------
    |
    | The HMAC-SHA256 secret used to verify incoming webhook requests from Feedple Cloud.
    | Supports comma-separated secrets for zero-downtime secret rotation.
    |
    */
    'webhook_secret' => env('FEEDPLE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Transport Mode
    |--------------------------------------------------------------------------
    |
    | 'webhook'   - Stateless HTTP bridge endpoint (default for shared hosting & serverless).
    | 'websocket' - Persistent WebSocket background worker daemon.
    |
    */
    'transport' => env('FEEDPLE_TRANSPORT', 'webhook'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Bridge Path
    |--------------------------------------------------------------------------
    |
    | The relative URI path where Feedple Cloud sends signed IR query requests.
    |
    */
    'bridge_path' => env('FEEDPLE_BRIDGE_PATH', '/api/feedple/bridge'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | Feedple Cloud REST API base URL for one-shot schema synchronization.
    |
    */
    'api_base_url' => env('FEEDPLE_API_BASE_URL', env('FEEDPLE_API_URL', 'https://feedple-ai-614817435356.us-central1.run.app/api/v1')),
];

