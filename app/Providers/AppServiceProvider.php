<?php

namespace App\Providers;

use App\Contracts\BlockchainClient;
use App\Services\Address\AddressGeneratorFactory;
use App\Services\Address\EthereumAddressGenerator;
use App\Services\Address\TronAddressGenerator;
use App\Services\Blockchain\BlockchainClientFactory;
use App\Services\Blockchain\EthereumClient;
use App\Services\TokenConfigService;
use Illuminate\Support\ServiceProvider;
use App\Contracts\AddressGeneratorInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BlockchainClient::class, EthereumClient::class);
        // Address generators
        $this->app->bind(AddressGeneratorInterface::class . ':ETH', EthereumAddressGenerator::class);
        $this->app->bind(AddressGeneratorInterface::class . ':tron', TronAddressGenerator::class);
        // Фабрика генераторов адресов
        $this->app->singleton(AddressGeneratorFactory::class, function ($app) {
            return new AddressGeneratorFactory([
                'ETH'  => AddressGeneratorInterface::class . ':ETH',
                'USDT' => AddressGeneratorInterface::class . ':ETH', // USDT использует Ethereum-адреса
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
