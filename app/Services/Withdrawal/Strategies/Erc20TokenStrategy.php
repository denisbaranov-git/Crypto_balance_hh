<?php
// app/Services/Withdrawal/Strategies/Erc20TokenStrategy.php

namespace App\Services\Withdrawal\Strategies;

use App\Contracts\WithdrawalStrategy;
use App\Contracts\BlockchainClient;
use App\Models\CryptoTransaction;

class Erc20TokenStrategy implements WithdrawalStrategy
{
    public function __construct(
        private BlockchainClient $client,
        private string $contractAddress,
        private array $supportedCurrencies = ['USDT', 'USDC', 'DAI']
    ) {}

    public function send(CryptoTransaction $transaction, string $privateKey): string
    {
        $toAddress = $transaction->metadata['to'] ?? null;
        if (!$toAddress) {
            throw new \RuntimeException('Missing destination address');
        }

        return $this->client->sendToken(
            $privateKey,
            $toAddress,
            (float)$transaction->amount,
            $this->contractAddress,
            $transaction->wallet->decimals
        );
    }

    public function supports(string $currency): bool
    {
        return in_array($currency, $this->supportedCurrencies, true);
    }
}
