<?php
// app/Services/Blockchain/Clients/EthereumClient.php

namespace App\Services\Blockchain;

use App\Contracts\BlockchainClient;
use Elliptic\EC;
use kornrunner\Keccak;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EthereumClient implements BlockchainClient
{
    protected string $rpcUrl;
    protected int $chainId;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->rpcUrl = $config['rpc_url'] ?? throw new RuntimeException('RPC URL required for Ethereum client');
        $this->chainId = $config['chain_id'] ?? 1;
    }

    /**
     * Выполнить JSON-RPC запрос
     */
    protected function request(string $method, array $params = []): mixed
    {
        $response = Http::post($this->rpcUrl, [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("RPC request failed: {$response->body()}");
        }

        $data = $response->json();

        if (isset($data['error'])) {
            throw new RuntimeException("RPC error: {$data['error']['message']}");
        }

        return $data['result'];
    }

    /**
     * Конвертировать hex в десятичную строку
     */
    protected function hexToDec(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        return gmp_strval(gmp_init($hex, 16));
    }

    /**
     * Конвертировать десятичную строку в hex
     */
    protected function decToHex(string $dec): string
    {
        return '0x' . gmp_strval(gmp_init($dec, 10), 16);
    }

    /**
     * Получить адрес из приватного ключа
     */
    protected function privateKeyToAddress(string $privateKey): string
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->keyFromPrivate($privateKey);

        $publicKey = $keyPair->getPublic()->encode('hex', false);
        $publicKey = substr($publicKey, 2);

        return '0x' . substr(Keccak::hash(hex2bin($publicKey), 256), -40);
    }

    /**
     * Получить nonce (количество отправленных транзакций) для адреса
     */
    protected function getTransactionCount(string $address): string
    {
        $hexCount = $this->request('eth_getTransactionCount', [$address, 'pending']);
        return $this->hexToDec($hexCount);
    }

    /**
     * Получить текущую цену газа
     */
    protected function getGasPrice(): string
    {
        $hexPrice = $this->request('eth_gasPrice', []);
        return $this->hexToDec($hexPrice);
    }

    /**
     * Оценить лимит газа для транзакции
     */
    protected function estimateGas(string $from, string $to, string $data = '0x', string $value = '0x0'): string
    {
        $params = [
            'from' => $from,
            'to' => $to,
            'data' => $data,
            'value' => $value,
        ];

        $hexGas = $this->request('eth_estimateGas', [$params]);
        return $this->hexToDec($hexGas);
    }

    /**
     * Получить receipt транзакции
     */
    public function getTransactionReceipt(string $txid): ?array
    {
        try {
            $receipt = $this->request('eth_getTransactionReceipt', [$txid]);
            if (!$receipt) {
                return null;
            }

            return [
                'status' => $receipt['status'] ?? '0x0',
                'blockNumber' => hexdec($receipt['blockNumber'] ?? '0x0'),
                'gasUsed' => $this->hexToDec($receipt['gasUsed'] ?? '0x0'),
                'logs' => $receipt['logs'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get transaction receipt', ['txid' => $txid, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Получить информацию о транзакции
     */
    public function getTransaction(string $txid): ?array
    {
        try {
            return $this->request('eth_getTransactionByHash', [$txid]);
        } catch (\Exception $e) {
            Log::error('Failed to get transaction', ['txid' => $txid, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Получить баланс нативной монеты (ETH)
     */
    public function getNativeBalance(string $address): string
    {
        try {
            $hexBalance = $this->request('eth_getBalance', [$address, 'latest']);
            return $this->hexToDec($hexBalance);
        } catch (\Exception $e) {
            Log::error('Failed to get native balance', ['address' => $address, 'error' => $e->getMessage()]);
            return '0';
        }
    }

    /**
     * Получить баланс токена (ERC-20)
     */
    public function getTokenBalance(string $address, string $contractAddress): string
    {
        try {
            // data: function selector balanceOf(address) + адрес (32 байта)
            $data = '0x70a08231' . $this->padAddress($address);
            $hexBalance = $this->request('eth_call', [
                ['to' => $contractAddress, 'data' => $data],
                'latest'
            ]);
            return $this->hexToDec($hexBalance);
        } catch (\Exception $e) {
            Log::error('Failed to get token balance', [
                'address' => $address,
                'contract' => $contractAddress,
                'error' => $e->getMessage()
            ]);
            return '0';
        }
    }

    /**
     * Дополняет адрес до 32 байт (64 символа)
     */
    protected function padAddress(string $address): string
    {
        $address = ltrim($address, '0x');
        return str_pad($address, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Сформировать data для transfer
     */
    protected function buildTransferData(string $to, string $amountInWei): string
    {
        // 0xa9059cbb - селектор функции transfer(address,uint256)
        $methodId = 'a9059cbb';

        // Адрес получателя (32 байта)
        $toPadded = $this->padAddress($to);

        // Сумма (32 байта)
        $amountHex = gmp_strval(gmp_init($amountInWei, 10), 16);
        $amountPadded = str_pad($amountHex, 64, '0', STR_PAD_LEFT);

        return '0x' . $methodId . $toPadded . $amountPadded;
    }

    /**
     * Отправить нативные монеты (ETH)
     */
    public function sendNative(string $fromPrivateKey, string $to, string $amount): string
    {
        try {
            // Получаем адрес отправителя из приватного ключа
            $fromAddress = $this->privateKeyToAddress($fromPrivateKey);

            // Получаем nonce
            $nonce = $this->getTransactionCount($fromAddress);

            // Получаем цену газа
            $gasPrice = $this->getGasPrice();

            // Конвертируем сумму в wei (1 ETH = 10^18 wei)
            $valueInWei = bcmul($amount, '1000000000000000000', 0);

            // Строим транзакцию
            $transaction = [
                'nonce' => $this->decToHex($nonce),
                'gasPrice' => $this->decToHex($gasPrice),
                'gas' => '0x5208', // 21000 - стандартный лимит для ETH перевода
                'to' => $to,
                'value' => $this->decToHex($valueInWei),
                'data' => '0x',
                'chainId' => $this->decToHex((string)$this->chainId),
            ];

            // Подписываем и отправляем
            return $this->signAndSend($transaction, $fromPrivateKey);

        } catch (\Exception $e) {
            Log::error('Ethereum sendNative failed', [
                'to' => $to,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Отправить токен (ERC-20)
     */
    public function sendToken(
        string $fromPrivateKey,
        string $to,
        string $amount,
        string $contractAddress,
        int $decimals
    ): string {
        try {
            // Получаем адрес отправителя из приватного ключа
            $fromAddress = $this->privateKeyToAddress($fromPrivateKey);

            // Конвертируем сумму в минимальные единицы (согласно decimals)
            $amountInMinUnits = bcmul($amount, bcpow('10', (string)$decimals, 0), 0);

            // Формируем data для вызова transfer
            $data = $this->buildTransferData($to, $amountInMinUnits);

            // Получаем nonce
            $nonce = $this->getTransactionCount($fromAddress);

            // Получаем цену газа
            $gasPrice = $this->getGasPrice();

            // Оцениваем лимит газа для этой транзакции
            $gasLimit = $this->estimateGas($fromAddress, $contractAddress, $data);

            // Строим транзакцию
            $transaction = [
                'nonce' => $this->decToHex($nonce),
                'gasPrice' => $this->decToHex($gasPrice),
                'gas' => $this->decToHex($gasLimit),
                'to' => $contractAddress,
                'value' => '0x0',
                'data' => $data,
                'chainId' => $this->decToHex((string)$this->chainId),
            ];

            // Подписываем и отправляем
            return $this->signAndSend($transaction, $fromPrivateKey);

        } catch (\Exception $e) {
            Log::error('Ethereum sendToken failed', [
                'to' => $to,
                'amount' => $amount,
                'contract' => $contractAddress,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Подписать и отправить транзакцию
     */
    protected function signAndSend(array $transaction, string $privateKey): string
    {
        // RLP-кодирование (упрощённо - в реальном проекте используйте библиотеку)
        $rlpEncoded = $this->rlpEncode($transaction);

        // Хеширование Keccak-256
        $hash = Keccak::hash($rlpEncoded, 256);

        // Подпись на secp256k1
        $ec = new EC('secp256k1');
        $signature = $ec->sign($hash, $privateKey);

        // Вычисляем v (EIP-155)
        $v = $this->calculateV($signature->recoveryParam, $this->chainId);

        // Формируем подписанную транзакцию
        $signedTx = [
            ...$transaction,
            'v' => '0x' . dechex($v),
            'r' => '0x' . $signature->r->toString(16),
            's' => '0x' . $signature->s->toString(16),
        ];

        // RLP-кодируем подписанную
        $rawTx = $this->rlpEncode($signedTx);

        // Отправляем
        $txid = $this->request('eth_sendRawTransaction', ['0x' . bin2hex($rawTx)]);

        return $txid;
    }

    /**
     * RLP-кодирование (упрощённая версия - для демонстрации)
     * В реальном проекте используйте готовую библиотеку
     */
    protected function rlpEncode(array $data): string
    {
        // Это упрощение - настоящая RLP-кодировка сложнее
        $encoded = '';
        foreach ($data as $value) {
            if (is_string($value) && strpos($value, '0x') === 0) {
                $value = substr($value, 2);
                if (strlen($value) % 2 !== 0) {
                    $value = '0' . $value;
                }
                $bytes = hex2bin($value);
                $encoded .= chr(strlen($bytes)) . $bytes;
            } else {
                $encoded .= chr(strlen((string)$value)) . (string)$value;
            }
        }
        return $encoded;
    }

    /**
     * Вычислить v для подписи (EIP-155)
     */
    protected function calculateV(int $recoveryParam, int $chainId): int
    {
        return 35 + $recoveryParam + $chainId * 2;
    }

    /**
     * Получить входящие транзакции токена (через события)
     */
    public function getIncomingTokenTransactions(
        string $address,
        string $contractAddress,
        int $fromBlock,
        int $toBlock
    ): array {
        try {
            // Хеш события Transfer
            $transferEventTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3f';
            $toTopic = '0x' . $this->padAddress($address);

            $logs = $this->request('eth_getLogs', [[
                'address' => $contractAddress,
                'fromBlock' => '0x' . dechex($fromBlock),
                'toBlock' => '0x' . dechex($toBlock),
                'topics' => [$transferEventTopic, null, $toTopic],
            ]]);

            $transactions = [];
            foreach ($logs as $log) {
                $transactions[] = [
                    'txid' => $log['transactionHash'],
                    'from' => '0x' . substr($log['topics'][1], 26), // убираем паддинг
                    'to' => $address,
                    'value' => $this->hexToDec($log['data']),
                    'blockNumber' => hexdec($log['blockNumber']),
                ];
            }

            return $transactions;

        } catch (\Exception $e) {
            Log::error('Failed to get incoming token transactions', [
                'address' => $address,
                'contract' => $contractAddress,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Получить входящие нативные транзакции (ETH)
     * Примечание: для ETH нет события Transfer, поэтому используем более сложный подход
     */
    public function getIncomingNativeTransactions(string $address, int $fromBlock, int $toBlock): array
    {
        // Для нативного ETH сканирование блоков очень ресурсоёмко
        // Рекомендуется использовать внешний API (Etherscan) или
        // перейти на модель с депозитными адресами
        Log::warning('getIncomingNativeTransactions called but not fully implemented', [
            'address' => $address,
            'fromBlock' => $fromBlock,
            'toBlock' => $toBlock
        ]);

        // Заглушка - в реальном проекте нужно реализовать через eth_getLogs для события Transfer?
        // Для ETH такого события нет. Возможные решения:
        // 1. Использовать Etherscan API
        // 2. Парсить каждый блок в поисках транзакций с to = address
        // 3. Использовать депозитные адреса (каждый пользователь получает свой адрес)

        return [];
    }

    /**
     * Получить номер последнего блока
     */
    public function getLatestBlock(): int
    {
        try {
            $hexBlock = $this->request('eth_blockNumber');
            return hexdec($hexBlock);
        } catch (\Exception $e) {
            Log::error('Failed to get latest block', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Получить данные последнего блока (номер и хеш)
     */
    public function getLatestBlockData(): array
    {
        try {
            $block = $this->request('eth_getBlockByNumber', ['latest', false]);
            if (!$block) {
                throw new RuntimeException('Failed to fetch latest block');
            }
            return [
                'number' => hexdec($block['number']),
                'hash' => $block['hash'],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get latest block data', ['error' => $e->getMessage()]);
            return ['number' => 0, 'hash' => ''];
        }
    }

    /**
     * Получить данные блока по номеру
     */
    public function getBlockByNumber(int $blockNumber): ?array
    {
        try {
            $block = $this->request('eth_getBlockByNumber', ['0x' . dechex($blockNumber), false]);
            if (!$block) {
                return null;
            }
            return [
                'number' => hexdec($block['number']),
                'hash' => $block['hash'],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get block by number', [
                'blockNumber' => $blockNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
