<?php

namespace App\Providers;

use App\Contracts\BlockchainClient;
use App\Services\Withdrawal\Strategies\Erc20TokenStrategy;
use App\Services\Withdrawal\Strategies\EthereumNativeStrategy;
use App\Services\Withdrawal\WithdrawalStrategyFactory;
use Illuminate\Support\ServiceProvider;

class WithdrawalStrategyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(WithdrawalStrategyFactory::class, function ($app) {
            $factory = new WithdrawalStrategyFactory();

            // Добавляем стратегии
            $factory->addStrategy(
                new EthereumNativeStrategy($app->make(BlockchainClient::class))
            );

            $factory->addStrategy(
                new Erc20TokenStrategy(
                    $app->make(BlockchainClient::class),
                    config('currencies.usdt.contract')
                )
            );

            // Легко добавить новую:
            // $factory->addStrategy(new BitcoinStrategy(...));

            return $factory;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
