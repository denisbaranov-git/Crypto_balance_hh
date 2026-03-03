<?php

namespace App\Jobs;

use App\Models\CryptoTransaction;
use App\Services\AccountService;
use App\Services\Blockchain\BlockchainClientFactory;
use App\Services\TokenConfigService;
use App\Services\Wallet\WalletCreationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SendWithdrawalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $tries = 3;
    public $backoff = [5, 15, 60]; // Интервалы между попытками (секунды)

    public function __construct(
        private readonly CryptoTransaction $transaction,
        private readonly TokenConfigService $tokenConfigService
    ){}

    public function handle(
        BlockchainClientFactory $clientFactory,
        WalletCreationService $walletCreator,
        AccountService $accountService
    ): void {
        $transaction = CryptoTransaction::find($this->transaction->id);

        if (!$transaction || $transaction->status !== 'pending') {
            Log::info('Withdrawal job skipped: transaction not pending', [
                'transaction_id' => $this->transaction->id
            ]);
            return;
        }

        $wallet = $transaction->wallet;
        $user = $wallet->user;
        if (!$wallet) {
            throw new RuntimeException("Wallet not found for transaction {$transaction->id}");
        }

        $currency = $wallet->currency;
        $network = $wallet->network;
        $toAddress = $transaction->metadata['to'] ?? null;

        if (!$toAddress) {
            throw new RuntimeException('Missing destination address in transaction metadata');
        }

        try {
            $client = $clientFactory->make($network);

            $privateKey = $user->getNetworkKey($network);

            if (!$privateKey) {
                throw new RuntimeException('Private key not found');
            }

            $config = $this->tokenConfigService->getTokenNetworkConfig($currency, $network );

            if ($wallet->currency === $config['native_currency']) {
                $txid = $client->sendNative(
                    $privateKey,
                    $toAddress,
                    $transaction->amount // строка
                );
            } else {
                if (!$config) {
                    throw new RuntimeException(
                        "Token configuration not found for {$currency} on {$network}"
                    );
                }

                $txid = $client->sendToken(
                    $privateKey,
                    $toAddress,
                    $transaction->amount,
                    $config['contract'],
                    $config['decimals']
                );
            }

            if (!$txid) {
                throw new RuntimeException('Failed to send transaction - no txid returned');
            }

            $transaction->txid = $txid;
            $transaction->save();

            ConfirmTransactionJob::dispatch($transaction)->delay(now()->addMinutes(2));

            Log::info('Withdrawal transaction sent successfully', [
                'transaction_id' => $transaction->id,
                'txid' => $txid,
                'attempt' => $this->attempts()
            ]);

        } catch (\Exception $e) {
            Log::error('Withdrawal job failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            if ($this->attempts() >= $this->tries) {// не отправили ноде
                // Отменяем транзакцию и возвращаем средства
                $accountService->cancelTransaction($transaction);
                Log::warning('Withdrawal cancelled after max attempts', [
                    'transaction_id' => $transaction->id
                ]);
            }

            throw $e;
        }
    }
}
