<?php

return [
    'ethereum' => [
        'name' => 'Ethereum Mainnet',
        'chain_id' => 1,
        'rpc_url' => env('ETHEREUM_RPC_URL', 'https://mainnet.infura.io/v3/' . env('INFURA_PROJECT_ID')),
        'native_currency' => 'ETH',
        'explorer' => 'https://etherscan.io',
        'type' => 'evm',
    ],
    'bsc' => [
        'name' => 'Binance Smart Chain',
        'chain_id' => 56,
        'rpc_url' => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org/'),
        'native_currency' => 'BNB',
        'explorer' => 'https://bscscan.com',
        'type' => 'evm',
    ],
    'polygon' => [
        'name' => 'Polygon Mainnet',
        'chain_id' => 137,
        'rpc_url' => env('POLYGON_RPC_URL', 'https://polygon-rpc.com'),
        'native_currency' => 'MATIC',
        'explorer' => 'https://polygonscan.com',
        'type' => 'evm',
    ],
    'tron' => [
        'name' => 'Tron Mainnet',
        'rpc_url' => env('TRON_RPC_URL', 'https://api.trongrid.io'),
        'api_key' => env('TRONGRID_API_KEY'), // TronGrid требует API ключ [citation:2]
        'native_currency' => 'TRX',
        'explorer' => 'https://tronscan.org',
        'type' => 'tron',
    ],
];
