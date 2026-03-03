<?php

namespace App\Jobs;

use App\Models\Wallet;
use App\Models\CryptoTransaction;
use App\Services\AccountService;
use App\Contracts\BlockchainClient;
use App\Services\TokenConfigService;
use App\Services\Wallet\WalletCreationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Размер чанка для сканирования (количество блоков за один запрос).
     */
    private const SCAN_CHUNK_SIZE = 5000;

    public function __construct(
        private TokenConfigService $tokenConfigService
    ) {}
    public function handle(
        BlockchainClient $client,
        AccountService $accountService,
        WalletCreationService $walletCreationService,
    ): void {

        //$threshold	Частота сканирования	Риск пропустить транзакцию
        //1	Каждый блок (12 сек)	            Минимальный	Огромная нагрузка
        //10	        ~2 минуты	            Низкий	Приемлемо
        //100       	~20 минут	            Средний	Экономично
        //1000      	~3.5 часа	            Высокий	Очень экономично
        //100 блоков в Ethereum ≈ 20 минут (12 секунд на блок × 100)
        // need progressive scanning that depend balance and activities of user (last_activity) //denis

        $latestBlock = $client->getLatestBlock();

        $wallets = Wallet::whereNotNull('address')
            ->get()
            ->filter(fn(Wallet $w) => $w->needsScanning($latestBlock));

        foreach ($wallets as $wallet) {
            // Получаем конфигурацию для этой валюты и сети
            $config = $this->tokenConfigService->getTokenNetworkConfig(
                $wallet->currency,
                $wallet->network
            );

            if (!$config) {
                Log::warning('No config for wallet', [
                    'wallet_id' => $wallet->id,
                    'currency' => $wallet->currency,
                    'network' => $wallet->network
                ]);
                continue;
            }

            // Для нативных токенов (ETH, BNB, TRX) нет контракта
            $contractAddress = $config['contract'] ?? null;

            if ($contractAddress) {
                // Это токен (USDT, USDC и т.д.)
                $this->scanWallet($wallet, $client, $accountService, $contractAddress, $latestBlock);
            } else {
                // Это нативный токен (ETH, BNB, TRX)
                $this->scanNativeWallet($wallet, $client, $accountService, $latestBlock);
            }
        }

        // Также проверяем подтверждения для pending-транзакций
        $this->checkConfirmations($client, $accountService, $latestBlock);
    }

    /**
     * Сканирует один кошелёк, разбивая диапазон на чанки.
     */
    private function scanWallet(
        Wallet $wallet,
        BlockchainClient $client,
        AccountService $accountService,
        string $contractAddress,
        int $latestBlock
    ): void {
        $fromBlock = ($wallet->last_scanned_block ?? 0) + 1;
        $toBlock = $latestBlock;

        if ($fromBlock > $toBlock) {
            return;
        }

        // Проверка реорганизации перед началом сканирования
        if ($wallet->last_scanned_block_hash) {
            $savedBlock = $client->getBlockByNumber($wallet->last_scanned_block);
            if (!$savedBlock || $savedBlock['hash'] !== $wallet->last_scanned_block_hash) {
                //denis здесь нужно срочно блокировать аккаут пользователя что бы не было ДВОЙНОЙ ТРАТЫ!!!!!! До разбора событий. Также уведомление Push, sms, mail etc
                Log::info('Reorg detected for wallet', ['wallet_id' => $wallet->id]);
                $this->rollbackWallet($wallet, $client, $accountService);
                $fromBlock = ($wallet->last_scanned_block ?? 0) + 1;
            }
        }

        $currentStart = $fromBlock;

        while ($currentStart <= $toBlock) {
            $currentEnd = min($currentStart + self::SCAN_CHUNK_SIZE - 1, $toBlock);

            try {
                $transactions = $client->getIncomingTokenTransactions(
                    $wallet->address,
                    $contractAddress,
                    $currentStart,
                    $currentEnd
                );

                foreach ($transactions as $txData) {
                    $this->processTransaction($wallet, $txData, $accountService);
                }

                // Обновляем прогресс
                $wallet->last_scanned_block = $currentEnd;
                $wallet->save();

                // Небольшая пауза для соблюдения rate limits
                if ($currentEnd < $toBlock) {
                    sleep(1);
                }

            } catch (\Exception $e) {
                Log::error('Scan chunk failed', [
                    'wallet_id' => $wallet->id,
                    'from'      => $currentStart,
                    'to'        => $currentEnd,
                    'error'     => $e->getMessage(),
                ]);
                // Прерываем сканирование этого кошелька, прогресс сохранён до последнего успешного чанка
                break;
            }

            $currentStart = $currentEnd + 1;
        }

        // После завершения сохраняем хеш последнего блока
        $lastBlockData = $client->getBlockByNumber($wallet->last_scanned_block);
        if ($lastBlockData) {
            $wallet->last_scanned_block_hash = $lastBlockData['hash'];
            $wallet->save();
        }
    }

    /**
     * Обрабатывает одну найденную транзакцию.
     */
    private function processTransaction(Wallet $wallet, array $txData, AccountService $accountService): void
    {
        // Проверка на дубликат
        $exists = $wallet->transactions()
            ->where('txid', $txData['txid'])
            ->exists();

        if ($exists) {
            return;
        }

        // Конвертация суммы
        $amount = bcdiv(
            $txData['value'],
            bcpow('10', (string)$wallet->decimals, 0),
            $wallet->decimals
        );

        $metadata = [
            'block' => $txData['blockNumber'],
            'from'  => $txData['from'],
        ];

        $accountService->deposit($wallet, $amount, $txData['txid'], $metadata);

        Log::info('Deposit processed', [
            'wallet_id' => $wallet->id,
            'txid'      => $txData['txid'],
            'amount'    => $amount,
        ]);
    }

    /**
     * Откатывает кошелёк при реорганизации.
     */
    private function rollbackWallet(Wallet $wallet, BlockchainClient $client, AccountService $accountService): void
    {
        // Находим все pending-транзакции с номером блока > последнего стабильного
        $affectedTransactions = CryptoTransaction::where('wallet_id', $wallet->id)
            ->where('status', 'pending')
            ->whereNotNull('metadata->block')
            ->where('metadata->block', '>', $wallet->last_scanned_block)
            ->get();

        foreach ($affectedTransactions as $transaction) {
            $accountService->cancelTransaction($transaction);
        }

        // Возвращаемся на безопасный блок (например, на 100 блоков назад)
        $newStart = max(0, $wallet->last_scanned_block - 100);
        $wallet->last_scanned_block = $newStart;
        $wallet->last_scanned_block_hash = null;
        $wallet->save();
    }

    /**
     * Проверяет подтверждения для pending-транзакций.
     */
    private function checkConfirmations(BlockchainClient $client, AccountService $accountService, int $latestBlock): void
    {
        $pendingTxs = CryptoTransaction::where('status', 'pending')
            ->whereNotNull('metadata->block')
            ->get();

        foreach ($pendingTxs as $tx) {
            $txBlock = $tx->metadata['block'] ?? 0;
            if ($latestBlock - $txBlock >= self::REQUIRED_CONFIRMATIONS) { ////$config['confirmation_blocks']
                // Дополнительно проверить receipt (успех/неудача)
                $receipt = $client->getTransactionReceipt($tx->txid);
                if ($receipt && $receipt['status'] === '0x1') {
                    $accountService->confirmTransaction($tx, $tx->txid);
                }
                Log::info('Transaction confirmed', ['txid' => $tx->txid]);
            }
        }
    }
}
