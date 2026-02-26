<?php
// app/Services/Withdrawal/Strategies/EthereumNativeStrategy.php

namespace App\Services\Withdrawal\Strategies;

use App\Contracts\WithdrawalStrategy;
use App\Contracts\BlockchainClient;
use App\Models\CryptoTransaction;

class EthereumNativeStrategy implements WithdrawalStrategy
{
    public function __construct(
        private BlockchainClient $client
    ) {}

    public function send(CryptoTransaction $transaction, string $privateKey): string
    {
        $toAddress = $transaction->metadata['to'] ?? null;
        if (!$toAddress) {
            throw new \RuntimeException('Missing destination address');
        }

        return $this->client->sendNative(
            $privateKey,
            $toAddress,
            (float)$transaction->amount
        );
    }

    public function supports(string $currency): bool
    {
        return $currency === 'ETH';
    }
}
