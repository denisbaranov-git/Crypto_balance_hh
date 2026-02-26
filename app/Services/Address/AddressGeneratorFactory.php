<?php
// app/Services/Address/AddressGeneratorFactory.php

namespace App\Services\Address;

use App\Contracts\AddressGeneratorInterface;
use InvalidArgumentException;

class AddressGeneratorFactory
{
    protected array $map;

    public function __construct(array $map)
    {
        $this->map = $map;
    }

    /**
     * Возвращает генератор для указанной валюты.
     *
     * @param string $currency
     * @return AddressGeneratorInterface
     * @throws InvalidArgumentException
     */
    public function make(string $currency): AddressGeneratorInterface
    {
        if (!isset($this->map[$currency])) {
            throw new InvalidArgumentException("No address generator for currency: {$currency}");
        }

        return app($this->map[$currency]);
    }
}
