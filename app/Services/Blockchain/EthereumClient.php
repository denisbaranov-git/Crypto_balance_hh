<?php
// app/Services/Blockchain/Clients/EthereumClient.php

namespace App\Services\Blockchain;

use App\Contracts\BlockchainClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EthereumClient implements BlockchainClient
{
//    protected string $infuraUrl;
//    protected string $projectId;

    protected string $rpcUrl;
    protected int $chainId;

    /*
        //--- method
        Блоки: eth_blockNumber, eth_getBlockByNumber, eth_getBlockByHash.
        Транзакции: eth_getTransactionByHash, eth_getTransactionReceipt.
        Балансы: eth_getBalance (нативный токен), eth_call (для вызова контрактов).
        Логи (события): eth_getLogs.
        Отправка транзакций: eth_sendRawTransaction.

        //--- params
        Массивом (["0xадрес", "latest"] для eth_getBalance).
        Объектом (в eth_getLogs передаётся объект с полями address, fromBlock, toBlock, topics).
    */
//    public function __construct()
//    {
//        $this->projectId = config('services.infura.project_id');
//        if (empty($this->projectId)) {
//            throw new RuntimeException('Infura project ID not configured');
//        }
//        $this->infuraUrl = "https://mainnet.infura.io/v3/{$this->projectId}";
//    }
    public function __construct(array $config)
    {
        $this->rpcUrl = $config['rpc_url'];
        $this->chainId = $config['chain_id'] ?? 1;
    }
    /**
     * Выполнить JSON-RPC запрос.
     */
    protected function request(string $method, array $params = []): mixed
    {
        $response = Http::post($this->rpcUrl, [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
            'id'      => 1,
        ]);

        if ($response->failed()) {
            Log::error('Infura request failed', [
                'method' => $method,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new RuntimeException('Infura request failed: ' . $response->body());
        }

        $data = $response->json();

        if (isset($data['error'])) {
            Log::error('Infura error', ['error' => $data['error']]);
            throw new RuntimeException('Infura error: ' . ($data['error']['message'] ?? 'Unknown error'));
        }

        return $data['result'] ?? null;
    }

    /**
     * Конвертирует hex-строку в десятичную строку.
     */
    protected function hexToDec(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        return gmp_strval(gmp_init($hex, 16));
    }

    /**
     * Дополняет адрес до 32 байт (64 символа) для использования в data/topics.
     */
    protected function padAddress(string $address): string
    {
        $address = ltrim($address, '0x');
        return str_pad($address, 64, '0', STR_PAD_LEFT);
    }

    public function getNativeBalance(string $address): string
    {
        $hexBalance = $this->request('eth_getBalance', [$address, 'latest']);
        return $this->hexToDec($hexBalance);
    }

    public function getTokenBalance(string $address, string $contractAddress): string
    {
        // data: function selector balanceOf(address) + адрес (32 байта)
        $data = '0x70a08231' . $this->padAddress($address);
        $hexBalance = $this->request('eth_call', [
            ['to' => $contractAddress, 'data' => $data],
            'latest'
        ]);
        return $this->hexToDec($hexBalance);
    }

    public function sendNative(string $fromPrivateKey, string $to, float $amount): string
    {
        // В реальном проекте здесь подписание транзакции и отправка через eth_sendRawTransaction
        // Для тестового задания возвращаем заглушку
        return '0x' . bin2hex(random_bytes(32));
    }

    public function sendToken(string $fromPrivateKey, string $to, float $amount, string $contractAddress, int $decimals): string
    {
        // Заглушка
        return '0x' . bin2hex(random_bytes(32));
    }

    public function getIncomingTokenTransactions(string $address, string $contractAddress, int $fromBlock, int $toBlock): array
    {
        // Хеш события Transfer
        $transferEventTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3f';
        $toTopic = '0x' . $this->padAddress($address);

        $logs = $this->request('eth_getLogs', [[
            'address'   => $contractAddress,
            'fromBlock' => '0x' . dechex($fromBlock),
            'toBlock'   => '0x' . dechex($toBlock),
            'topics'    => [$transferEventTopic, null, $toTopic],
        ]]);

        $transactions = [];
        foreach ($logs as $log) {
            $transactions[] = [
                'txid'        => $log['transactionHash'],
                'from'        => '0x' . substr($log['topics'][1], 26),
                'to'          => $address,
                'value'       => $this->hexToDec($log['data']),
                'blockNumber' => hexdec($log['blockNumber']),
            ];
        }

        return $transactions;
    }

    public function getLatestBlock(): int
    {
        $hexBlock = $this->request('eth_blockNumber');
        return hexdec($hexBlock);
    }

    public function getLatestBlockData(): array
    {
        $block = $this->request('eth_getBlockByNumber', ['latest', false]);
        if (!$block) {
            throw new RuntimeException('Failed to fetch latest block');
        }
        return [
            'number' => hexdec($block['number']),
            'hash'   => $block['hash'],
        ];
    }

    public function getBlockByNumber(int $blockNumber): ?array
    {
        $block = $this->request('eth_getBlockByNumber', ['0x' . dechex($blockNumber), false]);
        if (!$block) {
            return null;
        }
        return [
            'number' => hexdec($block['number']),
            'hash'   => $block['hash'],
        ];
    }
}
