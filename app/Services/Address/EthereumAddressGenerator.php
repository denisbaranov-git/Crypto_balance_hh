<?php

namespace App\Services\Address;

use App\Contracts\AddressGeneratorInterface;
use Elliptic\EC;
use kornrunner\Keccak;

class EthereumAddressGenerator implements AddressGeneratorInterface
{
    public function generate(array $options = []): array
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();

        $privateKey = $keyPair->getPrivate()->toString(16, 64);
        $publicKey = $keyPair->getPublic()->encode('hex', false);
        $publicKey = substr($publicKey, 2); // убираем '04'

        $address = '0x' . substr(Keccak::hash(hex2bin($publicKey), 256), -40);

        return [
            'private_key' => $privateKey,
            'address'     => $address,
        ];
    }

    public function addressFromPrivateKey(string $privateKey): string
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->keyFromPrivate($privateKey);

        $publicKey = $keyPair->getPublic()->encode('hex', false);
        $publicKey = substr($publicKey, 2);

        return '0x' . substr(Keccak::hash(hex2bin($publicKey), 256), -40);
    }
}
