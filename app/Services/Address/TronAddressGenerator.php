<?php
// app/Services/Address/Generators/TronAddressGenerator.php

namespace App\Services\Address;

use App\Contracts\AddressGeneratorInterface;
use App\Services\Blockchain\TronClient;
use Trx\TronClient;

class TronAddressGenerator implements AddressGeneratorInterface
{
    protected TronClient $client;

    public function __construct()
    {
        $this->client = new TronClient(env('TRONGRID_API_KEY'));
    }

    public function generate(array $options = []): array
    {
        $wallet = $this->client->wallet()->create();

        return [
            'private_key' => $wallet->getPrivateKey(),
            'address' => $wallet->getAddress(), // Base58 адрес (начинается с T)
        ];
    }

    public function addressFromPrivateKey(string $privateKey): string
    {
        return $this->client->wallet()->fromPrivateKey($privateKey)->getAddress();
    }
}
