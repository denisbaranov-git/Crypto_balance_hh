<?php

namespace App\Jobs;

use App\Models\CryptoTransaction;
use App\Models\Wallet;
use App\Services\CryptoService;
use App\Contracts\BlockchainClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessIncomingTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Ключ для хранения данных о последнем обработанном блоке (номер и хеш).
     */
    private const LAST_BLOCK_DATA_KEY = 'usdt_last_processed_block_data';

    /**
     * Количество блоков, на которое откатываемся при реорганизации.
     * Должно быть больше максимальной глубины реорга (обычно 1-2, но берём с запасом).
     */
    private const REORG_SAFE_DEPTH = 100;

    /**
     * Количество подтверждений, после которых транзакция считается завершённой.
     */
    private const REQUIRED_CONFIRMATIONS = 12;

    public function handle(BlockchainClient $client, CryptoService $accountService): void
    {
        $contractAddress = config('currencies.usdt.contract');
        if (empty($contractAddress)) {
            Log::error('USDT contract address not configured');
            return;
        }

        // Получаем сохранённые данные о последнем обработанном блоке
        $lastData = Cache::get(self::LAST_BLOCK_DATA_KEY, ['number' => 0, 'hash' => null]);
        $lastBlockNumber = $lastData['number'];
        $lastBlockHash = $lastData['hash'];

        // Получаем текущий последний блок (номер и хеш)
        $latestBlockData = $client->getLatestBlockData(); // ['number' => int, 'hash' => string]

        // Проверяем, не было ли реорганизации, если у нас есть предыдущий хеш
        if ($lastBlockHash !== null && $lastBlockNumber <= $latestBlockData['number']) {
            $currentBlockAtLastNumber = $client->getBlockByNumber($lastBlockNumber);
            // Если блок с таким номером существует и его хеш не совпадает с сохранённым
            if ($currentBlockAtLastNumber && $currentBlockAtLastNumber['hash'] !== $lastBlockHash) {
                Log::info('Reorg detected at block ' . $lastBlockNumber . ', rolling back ' . self::REORG_SAFE_DEPTH . ' blocks');
                // Откатываемся на безопасную глубину
                $newStartBlock = max(0, $lastBlockNumber - self::REORG_SAFE_DEPTH);
                // Отменяем все транзакции, которые были в блоках выше $newStartBlock
                $this->rollbackTransactionsFrom($newStartBlock + 1, $accountService);
                $lastBlockNumber = $newStartBlock;
                // Хеш для нового стартового блока мы получим позже, при сохранении
            }
        }

        // Теперь обычное сканирование новых блоков
        $fromBlock = $lastBlockNumber + 1;
        $toBlock = $latestBlockData['number'];

        if ($fromBlock > $toBlock) {
            // Нет новых блоков, но нужно сохранить актуальный хеш последнего блока
            $this->updateLastBlockData($latestBlockData['number'], $latestBlockData['hash'], $client);
            return;
        }

        Log::info("Scanning blocks from $fromBlock to $toBlock");

        // Получаем все кошельки USDT с адресами
        $wallets = Wallet::where('currency', 'USDT')
            ->whereNotNull('address')
            ->get()
            ->keyBy('address'); // для быстрого поиска

        if ($wallets->isEmpty()) {
            // Если нет кошельков, просто обновляем последний блок
            $this->updateLastBlockData($toBlock, $latestBlockData['hash'], $client);
            return;
        }

        $maxProcessedBlock = $fromBlock - 1;

        // Для каждого адреса получаем входящие транзакции
        // В реальном проекте лучше использовать один запрос для всех адресов,
        // но для простоты оставим цикл.
        foreach ($wallets as $address => $wallet) {
            try {
                $transactions = $client->getIncomingTokenTransactions(
                    $address,
                    $contractAddress,
                    $fromBlock,
                    $toBlock
                );

                foreach ($transactions as $txData) {
                    // Проверяем, есть ли уже такая транзакция
                    $exists = $wallet->transactions()
                        ->where('txid', $txData['txid'])
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    // Конвертируем сумму из минимальных единиц в основные
                    $amount = bcdiv(
                        $txData['value'],
                        bcpow('10', (string)$wallet->decimals, 0),
                        $wallet->decimals
                    );

                    $metadata = [
                        'block' => $txData['blockNumber'],
                        'from'  => $txData['from'] ?? null,
                        'contract' => $contractAddress,
                    ];

                    // Зачисляем средства (статус pending)
                    $transaction = $accountService->deposit(
                        $wallet,
                        $amount,
                        $txData['txid'],
                        $metadata
                    );

                    Log::info('Incoming USDT credited', [
                        'wallet_id' => $wallet->id,
                        'txid'      => $txData['txid'],
                        'amount'    => $amount,
                    ]);

                    if ($txData['blockNumber'] > $maxProcessedBlock) {
                        $maxProcessedBlock = $txData['blockNumber'];
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error processing incoming transactions for wallet', [
                    'wallet_id' => $wallet->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // Определяем, какой блок считать последним обработанным
        // Если были транзакции, используем максимальный номер блока среди них, иначе используем toBlock
        $newLastBlock = ($maxProcessedBlock >= $fromBlock) ? $maxProcessedBlock : $toBlock;
        $this->updateLastBlockData($newLastBlock, null, $client);
    }

    /**
     * Сохраняет в кеше данные о последнем обработанном блоке.
     * Если хеш не передан, получает его через клиент.
     *
     * @param int $blockNumber
     * @param string|null $blockHash
     * @param BlockchainClient $client
     */
    private function updateLastBlockData(int $blockNumber, ?string $blockHash, BlockchainClient $client): void
    {
        if ($blockHash === null) {
            $blockData = $client->getBlockByNumber($blockNumber);
            $blockHash = $blockData['hash'] ?? null;
        }
        Cache::put(self::LAST_BLOCK_DATA_KEY, [
            'number' => $blockNumber,
            'hash'   => $blockHash,
        ]);
    }

    /**
     * Откатывает все транзакции, которые были созданы из блоков с номером >= $fromBlock.
     * Для каждой такой транзакции вызываем cancelTransaction().
     *
     * @param int $fromBlock
     * @param CryptoService $accountService
     */
    private function rollbackTransactionsFrom(int $fromBlock, CryptoService $accountService): void
    {
        // Находим все транзакции со статусом 'pending', у которых в metadata указан block >= $fromBlock
        $transactions = CryptoTransaction::where('status', 'pending')
            ->whereNotNull('metadata->block')
            ->where('metadata->block', '>=', $fromBlock)
            ->get();

        foreach ($transactions as $transaction) {
            try {
                $accountService->cancelTransaction($transaction);
                Log::info('Transaction cancelled due to reorg', [
                    'txid' => $transaction->txid,
                    'block' => $transaction->metadata['block'] ?? null,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to cancel transaction during reorg', [
                    'transaction_id' => $transaction->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }
    }
}
