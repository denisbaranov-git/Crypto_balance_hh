<?php
// app/Services/Blockchain/Clients/TronClient.php

namespace App\Services\Blockchain\Clients;

use App\Contracts\BlockchainClient;
use IEXBase\TronAPI\Tron;
use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\Exception\TronException;
use Illuminate\Support\Facades\Log;

class TronClient implements BlockchainClient
{
    protected Tron $tron;
    protected array $config;
    protected string $network;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->network = $config['network'] ?? 'mainnet';

        $this->initializeClient();
    }

    /**
     * Инициализация Tron клиента с правильной передачей API ключа
     */
    protected function initializeClient(): void
    {
        try {
            // Создаём опции для инициализации
            $options = [
                'network' => $this->network,
            ];

            // Добавляем API ключ, если есть
            if (!empty($this->config['api_key'])) {
                $options['api_key'] = $this->config['api_key'];
            }

            // Добавляем кастомные эндпоинты, если указаны
            if (!empty($this->config['full_node'])) {
                $options['endpoints'] = [
                    'full_node' => $this->config['full_node'],
                    'solidity_node' => $this->config['solidity_node'] ?? $this->config['full_node'],
                    'event_server' => $this->config['event_server'] ?? $this->config['full_node'],
                ];
            }

            // Инициализация Tron через статический метод init()
            // (рекомендуемый способ в sultanov-solutions/tron-api)
            $this->tron = Tron::init($options);

            Log::info('Tron client initialized successfully', [
                'network' => $this->network,
                'has_api_key' => !empty($this->config['api_key'])
            ]);

        } catch (TronException $e) {
            Log::error('Tron client initialization failed', [
                'network' => $this->network,
                'error' => $e->getMessage()
            ]);

            // Пробуем альтернативный способ через HttpProvider (fallback)
            $this->initializeWithProviders();
        }
    }

    /**
     * Альтернативный способ инициализации через провайдеры (для совместимости)
     */
    protected function initializeWithProviders(): void
    {
        try {
            $fullNode = new HttpProvider(
                $this->config['full_node'] ?? 'https://api.trongrid.io'
            );

            $solidityNode = new HttpProvider(
                $this->config['solidity_node'] ?? 'https://api.trongrid.io'
            );

            $eventServer = new HttpProvider(
                $this->config['event_server'] ?? 'https://api.trongrid.io'
            );

            $this->tron = new Tron($fullNode, $solidityNode, $eventServer);

            // Если есть API ключ, устанавливаем его через setHeaders если метод существует
            // (проверяем существование метода через method_exists)
            if (!empty($this->config['api_key'])) {
                $this->setApiKeyIfPossible($this->config['api_key']);
            }

        } catch (TronException $e) {
            throw new \RuntimeException('Failed to initialize Tron client: ' . $e->getMessage());
        }
    }

    /**
     * Попытка установить API ключ (если метод существует)
     */
    protected function setApiKeyIfPossible(string $apiKey): void
    {
        // Проверяем, есть ли метод setHeaders у провайдеров
        $reflector = new \ReflectionClass($this->tron);

        if ($reflector->hasProperty('fullNode')) {
            $fullNode = $reflector->getProperty('fullNode')->getValue($this->tron);
            if ($fullNode && method_exists($fullNode, 'setHeaders')) {
                $fullNode->setHeaders(['TRON-PRO-API-KEY' => $apiKey]);
            }
        }

        if ($reflector->hasProperty('solidityNode')) {
            $solidityNode = $reflector->getProperty('solidityNode')->getValue($this->tron);
            if ($solidityNode && method_exists($solidityNode, 'setHeaders')) {
                $solidityNode->setHeaders(['TRON-PRO-API-KEY' => $apiKey]);
            }
        }
    }

    /**
     * Получить баланс нативной монеты (TRX)
     */
    public function getNativeBalance(string $address): string
    {
        try {
            $this->tron->setAddress($address);
            $balance = $this->tron->getBalance(null, true); // true = в SUN
            return (string)$balance;
        } catch (TronException $e) {
            Log::error('Tron getNativeBalance failed', ['address' => $address]);
            return '0';
        }
    }

    /**
     * Получить баланс токена (TRC-20)
     */
    public function getTokenBalance(string $address, string $contractAddress): string
    {
        try {
            $contract = $this->tron->contract($contractAddress); // правильный способ
            $balance = $contract->balanceOf($address);

            // balanceOf возвращает число в основных единицах (уже с учётом decimals)
            // конвертируем в минимальные единицы для совместимости с интерфейсом
            $contractInfo = $contract->array();
            $decimals = $contractInfo['decimals'] ?? 6;

            // Переводим обратно в минимальные единицы (умножаем на 10^decimals)
            $balanceInMinUnits = bcmul($balance, bcpow('10', (string)$decimals, 0), 0);

            return $balanceInMinUnits;

        } catch (TronException $e) {
            Log::error('Tron getTokenBalance failed', [
                'address' => $address,
                'contract' => $contractAddress
            ]);
            return '0';
        }
    }

    /**
     * Отправить TRX
     */
    public function sendNative(string $fromPrivateKey, string $to, float $amount): string
    {
        try {
            $this->tron->setPrivateKey($fromPrivateKey);

            // В библиотеке send принимает сумму в TRX (не в SUN)
            $result = $this->tron->send($to, $amount);

            if (isset($result['result']) && $result['result'] === true) {
                return $result['txid'] ?? $result['txID'] ?? '';
            }

            throw new TronException('Transaction failed');

        } catch (TronException $e) {
            Log::error('Tron sendNative failed', ['to' => $to, 'amount' => $amount]);
            throw $e;
        }
    }

    /**
     * Отправить TRC-20 токен (USDT)
     */
    public function sendToken(string $fromPrivateKey, string $to, float $amount, string $contractAddress, int $decimals): string
    {
        try {
            $this->tron->setPrivateKey($fromPrivateKey);

            // Получаем контракт
            $contract = $this->tron->contract($contractAddress);

            // Отправляем токены (сумма в основных единицах)
            $result = $contract->transfer($to, $amount);

            if (isset($result['result']) && $result['result'] === true) {
                return $result['txid'] ?? '';
            }

            throw new TronException('Token transfer failed: ' . ($result['message'] ?? 'Unknown error'));

        } catch (TronException $e) {
            Log::error('Tron sendToken failed', [
                'to' => $to,
                'amount' => $amount,
                'contract' => $contractAddress
            ]);
            throw $e;
        }
    }

    /**
     * Получить входящие транзакции токена
     */
    public function getIncomingTokenTransactions(string $address, string $contractAddress, int $fromBlock, int $toBlock): array
    {
        try {
            // Используем метод getEvents из библиотеки
            $events = $this->tron->getEvents($contractAddress, [
                'event_name' => 'Transfer',
                'from_block' => $fromBlock,
                'to_block' => $toBlock
            ]);

            $transactions = [];
            foreach ($events as $event) {
                // Парсим события Transfer
                if (isset($event['result']) &&
                    isset($event['result']['to']) &&
                    strtolower($event['result']['to']) === strtolower($address)) {

                    $transactions[] = [
                        'txid' => $event['transaction_id'],
                        'from' => $event['result']['from'] ?? '',
                        'to' => $address,
                        'value' => $this->amountToMinUnits(
                            $event['result']['value'] ?? '0',
                            $this->getDecimals($contractAddress)
                        ),
                        'blockNumber' => (int)($event['block_number'] ?? 0),
                    ];
                }
            }

            return $transactions;

        } catch (TronException $e) {
            Log::error('Tron getIncomingTokenTransactions failed', [
                'address' => $address,
                'contract' => $contractAddress
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
            $block = $this->tron->getCurrentBlock();
            return (int)($block['block_header']['raw_data']['number'] ?? 0);
        } catch (TronException $e) {
            return 0;
        }
    }

    /**
     * Получить данные последнего блока
     */
    public function getLatestBlockData(): array
    {
        try {
            $block = $this->tron->getCurrentBlock();
            return [
                'number' => (int)($block['block_header']['raw_data']['number'] ?? 0),
                'hash' => $block['blockID'] ?? '',
            ];
        } catch (TronException $e) {
            return ['number' => 0, 'hash' => ''];
        }
    }

    /**
     * Получить данные блока по номеру
     */
    public function getBlockByNumber(int $blockNumber): ?array
    {
        try {
            $block = $this->tron->getBlockByNumber($blockNumber);
            if (!$block) return null;

            return [
                'number' => $blockNumber,
                'hash' => $block['blockID'] ?? '',
            ];
        } catch (TronException $e) {
            return null;
        }
    }

    /**
     * Вспомогательный метод для получения decimals контракта
     */
    protected function getDecimals(string $contractAddress): int
    {
        try {
            $contract = $this->tron->contract($contractAddress);
            $info = $contract->array();
            return (int)($info['decimals'] ?? 6);
        } catch (\Exception $e) {
            return 6; // default для USDT
        }
    }

    /**
     * Конвертирует сумму из основных единиц в минимальные
     */
    protected function amountToMinUnits(string $amount, int $decimals): string
    {
        return bcmul($amount, bcpow('10', (string)$decimals, 0), 0);
    }

    /**
     * Конвертирует hex в десятичную строку
     */
    protected function hexToDec(string $hex): string
    {
        $hex = ltrim($hex, '0x');
        return gmp_strval(gmp_init($hex, 16));
    }
}
