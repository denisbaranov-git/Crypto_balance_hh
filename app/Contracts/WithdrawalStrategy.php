<?php

namespace App\Contracts;

use App\Models\CryptoTransaction;

interface WithdrawalStrategy
{
    /**
     * Отправляет транзакцию в блокчейн.
     *
     * @param CryptoTransaction $transaction
     * @param string $privateKey
     * @return string TXID
     * @throws \Exception
     */
    public function send(CryptoTransaction $transaction, string $privateKey): string;

    /**
     * Возвращает поддерживаемую валюту.
     */
    public function supports(string $currency): bool;
}
