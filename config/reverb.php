<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Reverb Server
    |--------------------------------------------------------------------------
    */

    'default' => env('REVERB_SERVER', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Reverb Servers
    |--------------------------------------------------------------------------
    */

    'servers' => [

        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'hostname' => env('REVERB_HOST'),
            'options' => [
                'tls' => [
                    'local_cert' => env('REVERB_TLS_CERT'),
                    'local_pk' => env('REVERB_TLS_KEY'),
                ],
            ],
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
            ],
            'pulse_ingest_interval' => env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10000),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb Applications
    |--------------------------------------------------------------------------
    */

    'apps' => [

        'provider' => 'config',

        'apps' => [
            [
                'key' => env('REVERB_APP_KEY'),
                'secret' => env('REVERB_APP_SECRET'),
                'app_id' => env('REVERB_APP_ID'),
                'allowed_origins' => ['*'],
                'ping_interval' => env('REVERB_SERVER_PING_INTERVAL', 30),
                'max_message_size' => env('REVERB_MAX_REQUEST_SIZE', 10000),
                'options' => [
                    'host' => env('REVERB_HOST'),
                    'port' => env('REVERB_PORT', 443),
                    'scheme' => env('REVERB_SCHEME', 'https'),
                    'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
                ],
                // ✅ OPTIMIZATION: Connection limits
                'capacity' => [
                    'max_connections' => env('REVERB_MAX_CONNECTIONS', 10000),
                    'max_backend_events_per_sec' => env('REVERB_MAX_BACKEND_EVENTS_PER_SEC', 1000),
                    'max_client_messages_per_sec' => env('REVERB_MAX_CLIENT_MESSAGES_PER_SEC', 100),
                    'max_read_buffer' => env('REVERB_MAX_READ_BUFFER', 10240),
                ],
            ],
        ],

    ],

];
