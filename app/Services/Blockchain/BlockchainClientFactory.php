<?php

namespace App\Services\Blockchain;

use App\Contracts\BlockchainClient;
use App\Services\TokenConfigService;
use InvalidArgumentException;

class BlockchainClientFactory
{
    public function __construct(
        private TokenConfigService $tokenConfigService,
        private array $clientMap
    ) {}

    public function make(string $network): BlockchainClient
    {
        if (!isset($this->clientMap[$network])) {
            throw new InvalidArgumentException("No client for network: {$network}");
        }

        $clientClass = $this->clientMap[$network];
        $networkConfig = $this->tokenConfigService->getNetworkConfig($network);

        return app($clientClass, ['config' => $networkConfig]);
    }
}
