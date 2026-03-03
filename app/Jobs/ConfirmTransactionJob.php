<?php

namespace App\Jobs;

use App\Models\CryptoTransaction;
use App\Services\AccountService;
use App\Services\Blockchain\BlockchainClientFactory;
use App\Services\TokenConfigService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConfirmTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(
        private CryptoTransaction $transaction,
        private TokenConfigService $tokenConfigService
    ){}

    public function handle(
        BlockchainClientFactory $clientFactory,
        AccountService $accountService
    ): void {
        $transaction = CryptoTransaction::find($this->transaction->id);

        if (!$transaction || $transaction->status !== 'pending') {
            return;
        }

        $wallet = $transaction->wallet;
        $client = $clientFactory->make($wallet->network);

        // Получаем номер блока транзакции
        $txBlock = $transaction->metadata['block'] ?? null;

        if (!$txBlock) {
            $receipt = $client->getTransactionReceipt($transaction->txid);// Сначала проверяем receipt (уже в блоке?)

            if ($receipt) { // Транзакция в блоке

                $txBlock = $receipt['blockNumber'];
                $transaction->metadata = array_merge($transaction->metadata ?? [], [
                    'block' => $txBlock,
                    'gasUsed' => $receipt['gasUsed'] ?? null,
                    'status' => $receipt['status'] ?? null,
                ]);
                $transaction->save();
            } else {
                // Нет receipt - проверяем, жива ли транзакция в mempool
                $txData = $client->getTransaction($transaction->txid);

                if (!$txData) {
                    // Транзакция исчезла из mempool и не в блоке! транзакции пришела пизда!!!
                    $this->handleMissingTransaction($transaction, $accountService);
                    return;
                }

                // Транзакция всё ещё в mempool - проверяем возраст
                $ageInHours = $transaction->created_at->diffInHours(now());

                //denis - хуй его знает 24 часа конечно слишком долго!!!
                // желательно сделать меньше, но пока пересрахуемся. Ищу дпруфы

                if ($ageInHours > 24) {
                    // Слишком долго в mempool - отменяем
                    Log::warning('Transaction stuck in mempool for >24h', [
                        'txid' => $transaction->txid,
                        'age' => $ageInHours
                    ]);
                    $accountService->cancelTransaction($transaction);
                    return;
                }

                // Всё нормально, ждём дальше
                $this->retryLater($transaction);
                return;
            }
        }

        // планирую ис пользовать финализацию блоков в сетях где поддерживается ethereum, bsc ..
        // Транзакция в блоке - считаем подтверждения
        $currentBlock = $client->getLatestBlock();
        $confirmations = $currentBlock - $txBlock;

        $config = $this->tokenConfigService->getTokenNetworkConfig($wallet->currency, $wallet->network);
        $required = $config['confirmation_blocks'] ?? 25;

        if ($confirmations >= $required) {
            $accountService->confirmTransaction($transaction, $transaction->txid);
            Log::info('Transaction confirmed', [
                'txid' => $transaction->txid,
                'confirmations' => $confirmations
            ]);
        } else {
            Log::debug('Transaction awaiting confirmations', [
                'txid' => $transaction->txid,
                'current' => $confirmations,
                'required' => $required
            ]);
            $this->retryLater($transaction);
        }
    }

    protected function handleMissingTransaction(CryptoTransaction $transaction, AccountService $accountService): void
    {
        Log::error('Transaction disappeared from mempool and not in block', [
            'txid' => $transaction->txid,
            'created_at' => $transaction->created_at,
            'age' => $transaction->created_at->diffInHours(now())
        ]);

        // Отменяем транзакцию и возвращаем средства
        $accountService->cancelTransaction($transaction);
    }

    protected function retryLater(CryptoTransaction $transaction): void
    {
        // Увеличиваем интервал с каждой попыткой
        $attempts = $transaction->metadata['confirmation_attempts'] ?? 0;
        $attempts++;

        $transaction->metadata = array_merge($transaction->metadata ?? [], [
            'confirmation_attempts' => $attempts
        ]);
        $transaction->save();

        // Экспоненциальная задержка: 2мин, 5мин, 10мин, 30мин, 1час...
        $delays = [2, 5, 10, 30, 60, 120, 240];
        $delay = $delays[min($attempts - 1, count($delays) - 1)];

        self::dispatch($transaction)->delay(now()->addMinutes($delay));
    }}
