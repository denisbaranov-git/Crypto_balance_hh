<?php
// app/Services/Blockchain/Clients/TronClient.php

namespace App\Services\Blockchain;

use App\Contracts\BlockchainClient;
use Trx\TronClient as TronApiClient;
use Trx\TronAddress;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TronClient implements BlockchainClient
{
    protected TronApiClient $client;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;

        // Инициализация Tron API клиента
        // Используем библиотеку just-Luka/php-tron [citation:2]
        $apiKey = $config['api_key'] ?? env('TRONGRID_API_KEY');
        $this->client = new TronApiClient($apiKey);
    }

    /**
     * Получить баланс нативной монеты (TRX)
     * @param string $address
     * @return string Баланс в SUN (минимальная единица TRX)
     */
    public function getNativeBalance(string $address): string
    {
        try {
            $account = $this->client->account($address);
            $balance = $account->explore()->balance; // в SUN
            return (string) $balance;
        } catch (\Exception $e) {
            Log::error('Failed to get TRX balance', [
                'address' => $address,
                'error' => $e->getMessage()
            ]);
            return '0';
        }
    }

    /**
     * Получить баланс токена (TRC-20)
     * @param string $address
     * @param string $contractAddress
     * @return string
     */
    public function getTokenBalance(string $address, string $contractAddress): string
    {
        try {
            // Для TRC-20 токенов нужно использовать контракт
            $contract = $this->client->contract($contractAddress);
            $balance = $contract->call('balanceOf', $address);
            return (string) $balance;
        } catch (\Exception $e) {
            Log::error('Failed to get token balance on Tron', [
                'address' => $address,
                'contract' => $contractAddress,
                'error' => $e->getMessage()
            ]);
            return '0';
        }
    }

    /**
     * Отправить нативные TRX
     */
    public function sendNative(string $fromPrivateKey, string $to, float $amount): string
    {
        try {
            // Конвертируем TRX в SUN (1 TRX = 1_000_000 SUN)
            $amountInSun = $amount * 1_000_000;

            $transaction = $this->client->sendTrx(
                $fromPrivateKey,
                $to,
                (int) $amountInSun
            );

            return $transaction['txid'] ?? '';
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to send TRX: " . $e->getMessage());
        }
    }

    /**
     * Отправить TRC-20 токен (например, USDT на Tron)
     */
    public function sendToken(string $fromPrivateKey, string $to, float $amount, string $contractAddress, int $decimals): string
    {
        try {
            // Конвертируем в минимальные единицы
            $amountInMinUnits = $amount * (10 ** $decimals);

            $transaction = $this->client->contract($contractAddress)
                ->callMethod('transfer', [
                    $to,
                    (int) $amountInMinUnits
                ], $fromPrivateKey);

            return $transaction['txid'] ?? '';
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to send TRC-20 token: " . $e->getMessage());
        }
    }

    /**
     * Получить входящие транзакции токена
     */
    public function getIncomingTokenTransactions(string $address, string $contractAddress, int $fromBlock, int $toBlock): array
    {
        try {
            // TronGrid API предоставляет фильтрацию транзакций [citation:2]
            $transactions = $this->client->account($address)
                ->filterLimit(100)
                ->filterContractAddress($contractAddress)
                ->filterFromBlock($fromBlock)
                ->filterToBlock($toBlock)
                ->transactions();

            return array_map(function ($tx) {
                return [
                    'txid' => $tx['txID'],
                    'from' => $tx['from'] ?? '',
                    'to' => $tx['to'] ?? '',
                    'value' => (string) ($tx['value'] ?? 0),
                    'blockNumber' => $tx['blockNumber'] ?? 0,
                ];
            }, $transactions);
        } catch (\Exception $e) {
            Log::error('Failed to get incoming Tron transactions', [
                'address' => $address,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Получить номер последнего блока
     */
    public function getLatestBlock(): int
    {
        try {
            $block = $this->client->block()->getLatest();
            return $block['blockNumber'] ?? 0;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get latest block: " . $e->getMessage());
        }
    }

    /**
     * Получить данные последнего блока
     */
    public function getLatestBlockData(): array
    {
        try {
            $block = $this->client->block()->getLatest();
            return [
                'number' => $block['blockNumber'] ?? 0,
                'hash' => $block['blockID'] ?? '',
            ];
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to get latest block data: " . $e->getMessage());
        }
    }

    /**
     * Получить данные блока по номеру
     */
    public function getBlockByNumber(int $blockNumber): ?array
    {
        try {
            $block = $this->client->block()->getByNumber($blockNumber);
            if (!$block) {
                return null;
            }
            return [
                'number' => $block['blockNumber'],
                'hash' => $block['blockID'],
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Получить receipt транзакции
     */
    public function getTransactionReceipt(string $txid): ?array
    {
        try {
            $tx = $this->client->transaction($txid);
            return [
                'blockNumber' => '0x' . dechex($tx['blockNumber'] ?? 0),
                'blockHash' => $tx['blockID'] ?? '',
                'status' => $tx['ret'][0]['contractRet'] ?? '',
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
