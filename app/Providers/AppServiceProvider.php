<?php

namespace App\Providers;

use App\Contracts\BlockchainClient;
use App\Services\AddressGeneratorFactory;
use App\Services\AddressGenerators\EthereumAddressGenerator;
use App\Services\Blockchain\EthereumClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BlockchainClient::class, EthereumClient::class);
        $this->app->singleton(AddressGeneratorFactory::class, function ($app) {
            return new AddressGeneratorFactory([
                'ETH' => EthereumAddressGenerator::class,
                //'BTC' => BitcoinAddressGenerator::class,
            ]);
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
