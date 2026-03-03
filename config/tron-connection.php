<?php

return [
    'default' => env('TRON_DEFAULT', 'mainnet'),

    'connections' => [
        'mainnet' => [
            'network' => 'mainnet',
            'endpoints' => [
                'full_node' => env('TRON_FULLNODE') ?: 'https://api.trongrid.io',
                'solidity_node' => env('TRON_SOLIDITY_NODE') ?: 'https://api.trongrid.io',
                'event_server' => env('TRON_EVENT_SERVER') ?: 'https://api.trongrid.io',
                'status_page' => '/',
            ],
            'timeout_ms' => env('TRON_TIMEOUT_MS') ?: 30000,
            'headers' => [],
            // API key(s) for TronGrid/Tronscan.
            // You can set TRON_API_KEY as comma-separated list to enable rotation.
            // Example: TRON_API_KEY=key1,key2,key3
            'api_key' => env('TRON_API_KEY'), // string or comma-separated list; overrides api_keys
            // 'api_keys' => ['key1','key2','key3'], // explicit list alternative
            'auth' => [
                // 'basic' => [env('TRON_BASIC_USER'), env('TRON_BASIC_PASS')],
                // 'bearer' => env('TRON_BEARER_TOKEN'),
            ],
            // 'private_key' => env('TRON_PRIVATE_KEY'),
            // 'address' => env('TRON_ADDRESS'),
        ],

        'shasta' => [
            'network' => 'shasta',
            'endpoints' => [
                'full_node' => 'https://api.shasta.trongrid.io',
                'solidity_node' => 'https://api.shasta.trongrid.io',
                'event_server' => 'https://api.shasta.trongrid.io',
                'status_page' => '/',
            ],
            'timeout_ms' => env('TRON_TIMEOUT_MS') ?: 30000,
            'headers' => [],
            'api_key' => env('TRON_API_KEY'),
            'auth' => [],
        ],

        'nile' => [
            'network' => 'nile',
            'endpoints' => [
                'full_node' => 'https://nile.trongrid.io',
                'solidity_node' => 'https://nile.trongrid.io',
                'event_server' => 'https://nile.trongrid.io',
                'status_page' => '/',
            ],
            'timeout_ms' => env('TRON_TIMEOUT_MS') ?: 30000,
            'headers' => [],
            'api_key' => env('TRON_API_KEY'),
            'auth' => [],
        ],
    ],
];
