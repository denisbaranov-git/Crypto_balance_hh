<?php

namespace App\Jobs;

use App\Models\CryptoTransaction;
use App\Contracts\BlockchainClient;
use App\Services\Wallet\WalletCreationService;
use App\Services\AccountService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConfirmTransactionJob
{
    use Dispatchable, InteractsWithQueue, SerializesModels;
    protected CryptoTransaction $transaction;
    public function __construct(CryptoTransaction $transaction)
    {
        $this->transaction = $transaction;
    }
        //После успешной отправки транзакция остаётся в статусе pending. Другой Job (например, ConfirmTransactionJob) будет:
        //Периодически проверять txid через eth_getTransactionReceipt.
        //При получении достаточного числа подтверждений вызывать confirmTransaction().
    public function handle(
        BlockchainClient $client,
        WalletCreationService $walletCreator,
        AccountService $accountService
    ): void {
        // Находим транзакцию (на случай, если она изменилась)
        $transaction = CryptoTransaction::find($this->transaction->id);

        if (!$transaction || $transaction->status !== 'pending') {
            Log::info('Withdrawal job skipped: transaction not pending', [
                'tx_id' => $this->transaction->id
            ]);
            return;
        }

        $wallet = $transaction->wallet;
        $user = $wallet->user;

        // Получаем приватный ключ пользователя
        $privateKey = $walletCreator->getPrivateKey($user, $wallet->currency);

        if (!$privateKey) {
            Log::error('Private key not found for withdrawal', [
                'user_id' => $user->id,
                'wallet_id' => $wallet->id
            ]);
            $this->fail("Private key not found");
            return;
        }

        try {
            $toAddress = $transaction->metadata['to'] ?? null;
            if (!$toAddress) {
                throw new \RuntimeException('Missing destination address');
            }

            // Отправка в зависимости от типа валюты
            if ($wallet->currency === 'ETH') {
                $txid = $client->sendNative(
                    $privateKey,
                    $toAddress,
                    (float)$transaction->amount
                );
            } else { // USDT (ERC-20)
                $txid = $client->sendToken(
                    $privateKey,
                    $toAddress,
                    (float)$transaction->amount,
                    config('currencies.usdt.contract'),
                    $wallet->decimals
                );
            }

            // Сохраняем txid (статус остаётся pending до подтверждения)
            $transaction->txid = $txid;
            $transaction->save();

            Log::info('Withdrawal transaction sent', [
                'tx_id' => $transaction->id,
                'txid' => $txid
            ]);

        } catch (\Exception $e) {
            Log::error('Withdrawal job failed', [
                'tx_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);

            // Если попытки исчерпаны — отменяем транзакцию и возвращаем средства
            if ($this->attempts() >= $this->tries) {
                $accountService->cancelTransaction($transaction);
                Log::warning('Withdrawal cancelled after max attempts', [
                    'tx_id' => $transaction->id
                ]);
            }

            throw $e; // Пробрасываем для повторной попытки
        }
    }

}
