<?php

namespace App\Services;

use App\Contracts\AddressGeneratorInterface;
use InvalidArgumentException;

class AddressGeneratorFactory
{
    protected array $map;

    public function __construct(array $map)
    {
        $this->map = $map;
    }

    public function make(string $currency): AddressGeneratorInterface
    {
        if (!isset($this->map[$currency])) {
            throw new InvalidArgumentException("Unsupported currency: $currency");
        }

        return app($this->map[$currency]);
    }
}
