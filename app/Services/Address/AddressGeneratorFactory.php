<?php

namespace App\Services\Address;

use App\Contracts\AddressGeneratorInterface;
use InvalidArgumentException;

class AddressGeneratorFactory
{
    /**
     * @var array Конфигурация генераторов
     * Формат: [
     *     'ETH' => 'service.name',
     *     'TRX' => 'service.name',
     *     'USDT' => [
     *         'ethereum' => 'service.name',
     *         'tron' => 'service.name',
     *     ],
     * ]
     */
    protected array $map;

    public function __construct(array $map)
    {
        $this->map = $map;
    }

    /**
     * Возвращает генератор для указанной валюты и сети.
     *
     * @param string $currency Валюта (ETH, USDT, TRX)
     * @param string $network Сеть (ethereum, tron, bsc)
     * @return AddressGeneratorInterface
     * @throws InvalidArgumentException
     */
    public function make(string $currency, string $network): AddressGeneratorInterface
    {
        if (!isset($this->map[$currency])) {
            throw new InvalidArgumentException(
                "No address generator configuration for currency: {$currency}"
            );
        }

        $config = $this->map[$currency];

        if (is_array($config)) {
            if (!isset($config[$network])) {
                throw new InvalidArgumentException(
                    "No address generator for currency {$currency} on network {$network}. " .
                    "Available networks: " . implode(', ', array_keys($config))
                );
            }

            $serviceName = $config[$network];
            return $this->resolveGenerator($serviceName, $currency, $network);
        }

        if (is_string($config)) {
            return $this->resolveGenerator($config, $currency, $network);
        }

        throw new InvalidArgumentException(
            "Invalid configuration format for currency: {$currency}"
        );
    }

    private function resolveGenerator(string $serviceName, string $currency, string $network): AddressGeneratorInterface
    {
        try {
            return app($serviceName);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                "Failed to resolve address generator for {$currency} on {$network}: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    public function supports(string $currency, string $network): bool
    {
        if (!isset($this->map[$currency])) {
            return false;
        }

        $config = $this->map[$currency];

        if (is_array($config)) {
            return isset($config[$network]);
        }

        return true;
    }

    /**
     * Возвращает список всех поддерживаемых валют.
     */
    public function getSupportedCurrencies(): array
    {
        return array_keys($this->map);
    }

    /**
     * Возвращает список сетей, поддерживаемых для валюты.
     */
    public function getSupportedNetworks(string $currency): array
    {
        if (!isset($this->map[$currency])) {
            return [];
        }

        $config = $this->map[$currency];

        if (is_array($config)) {
            return array_keys($config);
        }

        // Для строковых конфигураций возвращаем пустой массив,
        // так как точный список сетей неизвестен
        return [];
    }
}
