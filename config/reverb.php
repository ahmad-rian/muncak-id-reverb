<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Reverb Server
    |--------------------------------------------------------------------------
    |
    | This option controls the default server used by Reverb to handle
    | incoming messages as well as broadcasting message to all your
    | connected clients. At this time only "reverb" is supported.
    |
    */

    'default' => env('REVERB_SERVER', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Reverb Servers
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the Reverb servers that should be available
    | for your application.
    |
    */

    'servers' => [
        [
            'host' => env('REVERB_HOST', '0.0.0.0'),
            'port' => env('REVERB_PORT', 8080),
            'hostname' => env('REVERB_HOSTNAME', 'localhost'),
            'options' => [
                'tls' => [],
            ],
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
            ],
            'pulse_ingest_interval' => env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb Applications
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the Reverb applications.
    |
    */

    'apps' => [
        [
            'app_id' => env('REVERB_APP_ID', 'app-id'),
            'app_key' => env('REVERB_APP_KEY'),
            'app_secret' => env('REVERB_APP_SECRET'),
            'options' => [
                'host' => env('REVERB_HOST', '0.0.0.0'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'allowed_origins' => ['*'],
            'ping_interval' => env('REVERB_SERVER_PING_INTERVAL', 30),
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10000),

            // ✅ OPTIMIZATION: Connection pooling and limits
            'capacity' => [
                'max_connections' => env('REVERB_MAX_CONNECTIONS', 10000),
                'max_backend_events_per_sec' => env('REVERB_MAX_BACKEND_EVENTS_PER_SEC', 1000),
                'max_client_messages_per_sec' => env('REVERB_MAX_CLIENT_MESSAGES_PER_SEC', 100),
                'max_read_buffer' => env('REVERB_MAX_READ_BUFFER', 10240),
            ],
        ],
    ],
];
