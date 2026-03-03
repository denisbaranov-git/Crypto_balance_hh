<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Contracts\AddressGeneratorInterface;
use App\Services\Address\AddressGeneratorFactory;
use App\Services\Address\EthereumAddressGenerator;
use App\Services\Address\TronAddressGenerator;
use App\Services\Blockchain\BlockchainClientFactory;
use App\Services\Blockchain\EthereumClient;
use App\Services\Blockchain\TronClient;
use App\Services\TokenConfigService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AddressGeneratorInterface::class . ':ETH', EthereumAddressGenerator::class);
        $this->app->bind(AddressGeneratorInterface::class . ':TRON', TronAddressGenerator::class);
        // $this->app->bind(AddressGeneratorInterface::class . ':BSC', BscAddressGenerator::class);
        // $this->app->bind(AddressGeneratorInterface::class . ':SOLANA', SolanaAddressGenerator::class);

        $this->app->singleton(AddressGeneratorFactory::class, function ($app) {
            return new AddressGeneratorFactory([
                'ETH' => AddressGeneratorInterface::class . ':ETH',
                'TRX' => AddressGeneratorInterface::class . ':TRON',

                'USDT' => [
                    'ethereum' => AddressGeneratorInterface::class . ':ETH',
                    'tron'     => AddressGeneratorInterface::class . ':TRON',
                    'bsc'      => AddressGeneratorInterface::class . ':ETH',
                ],
                'USDC' => [
                    'ethereum' => AddressGeneratorInterface::class . ':ETH',
                    'bsc'      => AddressGeneratorInterface::class . ':ETH',
                ],
                'BNB' => AddressGeneratorInterface::class . ':ETH',
            ]);
        });

        $this->app->singleton(BlockchainClientFactory::class, function ($app) {
            return new BlockchainClientFactory(
                $app->make(TokenConfigService::class),
                [
                    'ethereum' => EthereumClient::class,
                    'bsc'      => EthereumClient::class,
                    'polygon'  => EthereumClient::class,
                    'tron'     => TronClient::class,
                ]
            );
        });

        $this->app->singleton(TokenConfigService::class, function ($app) {
            return new TokenConfigService();
        });
    }
}
