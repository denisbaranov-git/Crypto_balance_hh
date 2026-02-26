<?php

namespace App\Jobs;

use App\Models\CryptoTransaction;
use App\Services\AccountService;
use App\Services\Blockchain\BlockchainClientFactory;
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
    public $backoff = [5, 15, 60];

    protected CryptoTransaction $transaction;

    public function __construct(CryptoTransaction $transaction)
    {
        $this->transaction = $transaction;
    }

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
        if (!$wallet) {
            throw new RuntimeException("Wallet not found for transaction {$transaction->id}");
        }

        $network = $wallet->network;
        $toAddress = $transaction->metadata['to'] ?? null;

        if (!$toAddress) {
            throw new RuntimeException('Missing destination address in transaction metadata');
        }

        try {
            $client = $clientFactory->make($network);

            $privateKey = $walletCreator->getPrivateKey(
                $wallet->user,
                $wallet->currency,
                $network
            );

            if (!$privateKey) {
                throw new RuntimeException('Private key not found');
            }

            if ($wallet->currency === $this->getNativeCurrency($network)) {
                $txid = $client->sendNative(
                    $privateKey,
                    $toAddress,
                    $transaction->amount // строка
                );
            } else {
                $config = $wallet->tokenConfig;
                if (!$config) {
                    throw new RuntimeException(
                        "Token configuration not found for {$wallet->currency} on {$network}"
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

            if ($this->attempts() >= $this->tries) {
                // Отменяем транзакцию и возвращаем средства
                $accountService->cancelTransaction($transaction);
                Log::warning('Withdrawal cancelled after max attempts', [
                    'transaction_id' => $transaction->id
                ]);
            }

            throw $e;
        }
    }

    private function getNativeCurrency(string $network): string
    {
        return config("networks.{$network}.native_currency", 'ETH');
    }
}
