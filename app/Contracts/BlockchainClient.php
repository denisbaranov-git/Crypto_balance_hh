<?php

namespace App\Contracts;

interface BlockchainClient
{
    /**
     * Получить баланс нативной монеты (ETH, BNB и т.д.) для адреса.
     * @param string $address
     * @return string Баланс в минимальных единицах (wei, gwei) как строка.
     */
    public function getNativeBalance(string $address): string;

    /**
     * Получить баланс токена (ERC-20) для адреса.
     * @param string $address
     * @param string $contractAddress
     * @return string Баланс в минимальных единицах токена как строка.
     */
    public function getTokenBalance(string $address, string $contractAddress): string;

    /**
     * Отправить нативные монеты.
     * @param string $fromPrivateKey Приватный ключ отправителя (hex).
     * @param string $to
     * @param string $amount Сумма в основных единицах (например, ETH).
     * @return string TXID
     */
    public function sendNative(string $fromPrivateKey, string $to, string $amount): string;

    /**
     * Отправить токены (ERC-20).
     * @param string $fromPrivateKey
     * @param string $to
     * @param string $amount Сумма в основных единицах (например, USDT).
     * @param string $contractAddress
     * @param int $decimals Количество знаков после запятой.
     * @return string TXID
     */
    public function sendToken(string $fromPrivateKey, string $to, string $amount, string $contractAddress, int $decimals): string;

    /**
     * Получить входящие транзакции токена для адреса в диапазоне блоков.
     * @param string $address
     * @param string $contractAddress
     * @param int $fromBlock Начальный блок (включительно)
     * @param int $toBlock Конечный блок (включительно)
     * @return array Массив с полями: txid, from, to, value (строка в минимальных единицах), blockNumber (int)
     */
    public function getIncomingTokenTransactions(string $address, string $contractAddress, int $fromBlock, int $toBlock): array;

    /**
     * Получить номер последнего блока.
     * @return int
     */
    public function getLatestBlock(): int;

    /**
     * Получить данные последнего блока (номер и хеш).
     * @return array ['number' => int, 'hash' => string]
     */
    public function getLatestBlockData(): array;

    /**
     * Получить данные блока по номеру.
     * @param int $blockNumber
     * @return array|null ['number' => int, 'hash' => string] или null, если блок не найден
     */
    public function getBlockByNumber(int $blockNumber): ?array;
}
