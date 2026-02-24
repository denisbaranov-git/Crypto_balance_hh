<?php

namespace App\Contracts;

interface AddressGeneratorInterface
{
    /**
     * @param array $options extra param (example, type address for Bitcoin)
     * @return array ['address' => string, 'privateKey' => string]
     */
    public function generate(array $options = []): array;

    /**
     * @param string $privateKey
     * @return string
     */
    public function addressFromPrivateKey(string $privateKey): string;
}
