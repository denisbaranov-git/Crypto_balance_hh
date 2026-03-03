<?php

return [
    'full_node' => env('TRON_FULL_NODE', 'https://api.trongrid.io'),
    'solidity_node' => env('TRON_SOLIDITY_NODE', 'https://api.trongrid.io'),
    'event_server' => env('TRON_EVENT_SERVER', 'https://api.trongrid.io'),
    'api_key' => env('TRON_API_KEY'),
    'testnet' => env('TRON_TESTNET', false),

    // Контракты токенов (можно вынести в tokens.php)
    'contracts' => [
        'USDT' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
        'USDC' => 'TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8',
    ],
];
