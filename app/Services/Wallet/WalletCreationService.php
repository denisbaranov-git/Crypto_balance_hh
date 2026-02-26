<?php
// app/Services/Wallet/WalletCreationService.php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\UserNetworkKey;
use App\Models\Wallet;
use App\Services\Address\AddressGeneratorFactory;
use App\Services\TokenConfigService;
use App\Services\Blockchain\BlockchainClientFactory;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

class WalletCreationService
{
    public function __construct(
        private AddressGeneratorFactory $addressGeneratorFactory,
        private TokenConfigService $tokenConfigService,
        private BlockchainClientFactory $clientFactory
    ) {}

    public function createWallet(User $user, string $currency, string $network): Wallet
    {
        if (!$this->tokenConfigService->validateTokenNetwork($currency, $network)) {
            throw new InvalidArgumentException("Unsupported token/network: {$currency} on {$network}");
        }

        $config = $this->tokenConfigService->getTokenNetworkConfig($currency, $network);
        if (!$config) {
            throw new InvalidArgumentException("Unsupported token/network: {$currency} on {$network}");
        }

        $client = $this->clientFactory->make($network);
        $currentBlock = $client->getLatestBlock();

        return DB::transaction(function () use ($user, $currency, $network, $config, $currentBlock) {
            // Блокируем строку пользователя для этой сети
            $networkKey = UserNetworkKey::where('user_id', $user->id)
                ->where('network', $network)
                ->lockForUpdate()
                ->first();

            if (!$networkKey) {
                // Генерация ключа - локальная операция, не требует HTTP
                $generator = $this->addressGeneratorFactory->make($network);
                $keyData = $generator->generate();

                $networkKey = UserNetworkKey::create([
                    'user_id' => $user->id,
                    'network' => $network,
                    'encrypted_private_key' => Crypt::encryptString($keyData['private_key']),
                    'address' => $keyData['address'],
                ]);
            }

            // Проверяем, не создан ли уже кошелёк для этой валюты
            $existingWallet = Wallet::where('user_id', $user->id)
                ->where('currency', $currency)
                ->where('network', $network)
                ->lockForUpdate()
                ->first();

            if ($existingWallet) {
                throw new \RuntimeException("Wallet already exists for {$currency} on {$network}");
            }

            // Создаём кошелёк
            return Wallet::create([
                'user_id'                => $user->id,
                'currency'               => $currency,
                'network'                => $network,
                //'address'                => $networkKey->address,
                'balance'                => '0',
                'decimals'               => $config['decimals'],
                'last_scanned_block'     => $currentBlock,
                'last_scanned_block_hash' => null,
            ]);
        });
    }

    public function getPrivateKey(User $user, string $currency, string $network): ?string
    {
        $privateKeys = $user->crypto_private_keys ?? [];
        $encrypted = $privateKeys[$network][$currency] ?? null;
        return $encrypted ? Crypt::decryptString($encrypted) : null;
    }
}
