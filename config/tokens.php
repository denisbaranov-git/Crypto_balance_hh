<?php
// config/tokens.php

return [
    'USDT' => [
        'name' => 'Tether USD',
        'symbol' => 'USDT',
        'networks' => [
            'ethereum' => [
                'contract' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
                'decimals' => 6,
            ],
            'bsc' => [
                'contract' => '0x55d398326f99059ff775485246999027b3197955',
                'decimals' => 18,
            ],
            'tron' => [
                'contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                'decimals' => 6,
            ],
        ],
    ],
    'ETH' => [
        'name' => 'Ethereum',
        'symbol' => 'ETH',
        'networks' => [
            'ethereum' => [
                'decimals' => 18, // нативный
            ],
        ],
    ],
    'BNB' => [
        'name' => 'Binance Coin',
        'symbol' => 'BNB',
        'networks' => [
            'bsc' => [
                'decimals' => 18, // нативный для BSC
            ],
        ],
    ],
    'USDC' => [
        'name' => 'USD Coin',
        'symbol' => 'USDC',
        'networks' => [
            'ethereum' => [
                'contract' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
                'decimals' => 6,
            ],
            'bsc' => [
                'contract' => '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d',
                'decimals' => 18,
            ],
        ],
    ],
];
