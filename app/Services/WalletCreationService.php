<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Services\AddressGeneratorFactory;
use Illuminate\Support\Facades\Crypt;

class WalletCreationService
{
    public function __construct(
        private AddressGeneratorFactory $addressGeneratorFactory
    ) {}

    public function createWallet(User $user, string $currency): Wallet
    {
        $generator = $this->addressGeneratorFactory->make($currency);
        $keyData = $generator->generate();

        // Сохраняем приватный ключ зашифрованным
        $user->cryptoKeys()->updateOrCreate(
            ['currency' => $currency],
            ['private_key' => Crypt::encryptString($keyData['private_key'])]
        );

        // Создаём или обновляем кошелёк
        return Wallet::updateOrCreate(
            ['user_id' => $user->id, 'currency' => $currency],
            ['address' => $keyData['address'], 'balance' => '0']
        );
    }
}
