<?php
// app/Contracts/AddressGeneratorInterface.php

namespace App\Contracts;

interface AddressGeneratorInterface
{
    /**
     * Генерирует новую пару ключей и возвращает адрес и приватный ключ.
     * @param array $options Дополнительные параметры (тип адреса для Bitcoin и т.д.)
     * @return array ['address' => string, 'private_key' => string]
     */
    public function generate(array $options = []): array;

    /**
     * Восстанавливает адрес из приватного ключа.
     * @param string $privateKey
     * @return string
     */
    public function addressFromPrivateKey(string $privateKey): string;
}
