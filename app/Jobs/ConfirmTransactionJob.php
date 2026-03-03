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
            // Если нет блока (например, для исходящих), получаем receipt
            $receipt = $client->getTransactionReceipt($transaction->txid);
            if (!$receipt) {
                // Транзакция ещё не в блоке
                $this->retryLater($transaction);
                return;
            }
            $txBlock = $receipt['blockNumber'];
            $transaction->metadata = array_merge($transaction->metadata ?? [], [
                'block' => $txBlock,
                'gasUsed' => $receipt['gasUsed'] ?? null,
                'status' => $receipt['status'] ?? null,
            ]);
            $transaction->save();
        }

        // Получаем текущий блок
        $currentBlock = $client->getLatestBlock();
        $confirmations = $currentBlock - $txBlock;

        // Определяем требуемое количество подтверждений для этой сети/токена
        $config = $this->tokenConfigService->getTokenNetworkConfig($wallet->currency, $wallet->network);
        $required = $config['confirmation_blocks'];

        if ($confirmations >= $required) {
            // Достаточно подтверждений
            $accountService->confirmTransaction($transaction, $transaction->txid);
            Log::info('Transaction confirmed', [
                'txid' => $transaction->txid,
                'confirmations' => $confirmations
            ]);
        } else {
            // Недостаточно — перезапускаем позже
            $this->retryLater($transaction);

            Log::debug('Transaction awaiting confirmations', [
                'txid' => $transaction->txid,
                'current' => $confirmations,
                'required' => $required
            ]);
        }
    }

    protected function retryLater(CryptoTransaction $transaction): void
    {
        // Перезапускаем через 2 минуты
        self::dispatch($transaction)->delay(now()->addMinutes(2));
    }
}
