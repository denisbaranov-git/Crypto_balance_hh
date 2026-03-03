<?php

return [
    'ethereum' => [
        'name' => 'Ethereum Mainnet',
        'chain_id' => 1,
        'rpc_url' => env('ETHEREUM_RPC_URL', 'https://mainnet.infura.io/v3/' . env('INFURA_PROJECT_ID')),
        'native_currency' => 'ETH',
        'explorer' => 'https://etherscan.io',
        'type' => 'evm',
        'finalized ' => true,

    ],
    'bsc' => [
        'name' => 'Binance Smart Chain',
        'chain_id' => 56,
        'rpc_url' => env('BSC_RPC_URL', 'https://bsc-dataseed.binance.org/'),
        'native_currency' => 'BNB',
        'explorer' => 'https://bscscan.com',
        'type' => 'evm',
        'finalized ' => true,
    ],
    'polygon' => [
        'name' => 'Polygon Mainnet',
        'chain_id' => 137,
        'rpc_url' => env('POLYGON_RPC_URL', 'https://polygon-rpc.com'),
        'native_currency' => 'MATIC',
        'explorer' => 'https://polygonscan.com',
        'type' => 'evm',
        'finalized ' => true,

    ],
    'tron' => [
        'name' => 'Tron Mainnet',
        'network' => 'mainnet',  // для Tron::init()
        'full_node' => env('TRON_FULL_NODE', 'https://api.trongrid.io'),
        'solidity_node' => env('TRON_SOLIDITY_NODE', 'https://api.trongrid.io'),
        'event_server' => env('TRON_EVENT_SERVER', 'https://api.trongrid.io'),
        'api_key' => env('TRON_API_KEY'),  // будет передан в options['api_key']
        'native_currency' => 'TRX',
        'type' => 'tron',
        'finalized ' => false,
    ],
    'tron_shasta' => [
        'name' => 'Tron Shasta Testnet',
        'network' => 'shasta',
        'full_node' => env('TRON_SHASTA_FULL_NODE', 'https://api.shasta.trongrid.io'),
        'solidity_node' => env('TRON_SHASTA_SOLIDITY_NODE', 'https://api.shasta.trongrid.io'),
        'event_server' => env('TRON_SHASTA_EVENT_SERVER', 'https://api.shasta.trongrid.io'),
        'api_key' => env('TRON_SHASTA_API_KEY'),
        'native_currency' => 'TRX',
        'type' => 'tron',
        'testnet' => true,
        'finalized ' => false,
    ],
];
