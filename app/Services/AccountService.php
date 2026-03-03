<?php
// app/Services/AccountService.php

namespace App\Services;

use App\Models\Wallet;
use App\Models\CryptoTransaction;
use App\Enums\TransactionTypeEnum;
use App\Services\Blockchain\BlockchainClientFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AccountService
{
    public function __construct(
        private BlockchainClientFactory $clientFactory,
    ) {}
    private const SCALE = 8; // точность вычислений (должна совпадать с точностью хранения в БД)

    /**
     * Зачисление депозита (пополнение извне).
     */
    public function deposit(Wallet $wallet, string $amount, ?string $txid = null, array $metadata = []): CryptoTransaction
    {
        return $this->createTransaction($wallet, TransactionTypeEnum::DEPOSIT, $amount, $txid, $metadata);
    }

    /**
     * Вывод средств на внешний адрес.
     */
    public function withdraw(Wallet $wallet, string $amount, array $metadata = []): CryptoTransaction
    {
        return $this->createTransaction($wallet, TransactionTypeEnum::WITHDRAW, $amount, null, $metadata);
    }

    /**
     * Списание комиссии.
     */
    public function fee(Wallet $wallet, string $amount, ?string $txid = null, array $metadata = []): CryptoTransaction
    {
        return $this->createTransaction($wallet, TransactionTypeEnum::FEE, $amount, $txid, $metadata);
    }

    /**
     * Возврат средств (refund).
     */
    public function refund(Wallet $wallet, string $amount, ?string $txid = null, array $metadata = []): CryptoTransaction
    {
        return $this->createTransaction($wallet, TransactionTypeEnum::REFUND, $amount, $txid, $metadata);
    }

    /**
     * Подтверждение транзакции (после получения достаточного числа подтверждений).
     */
    public function confirmTransaction(CryptoTransaction $transaction, string $txid): void
    {
        DB::transaction(function () use ($transaction, $txid) {
            $transaction = CryptoTransaction::where('id', $transaction->id)->lockForUpdate()->first();

            if ($transaction->status !== 'pending') {
                return;
            }

            $transaction->txid = $txid;
            $transaction->status = 'completed';
            $transaction->save();
        });
    }

    /**
     * Отмена транзакции (при реорганизации или ошибке отправки).
     */
    public function cancelTransaction(CryptoTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {

            $transaction = CryptoTransaction::where('id', $transaction->id)->lockForUpdate()->first();

            if ($transaction->status !== 'pending') {
                return;
            }

            $wallet = Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->first();

            // Если транзакция уменьшала баланс (withdraw, fee) – восстанавливаем

            // Определяем, увеличивала или уменьшала транзакция баланс
            $isCredit = in_array($transaction->type, [
                TransactionTypeEnum::DEPOSIT->value,
                TransactionTypeEnum::REFUND->value
            ], true);

            if ($isCredit) {
                // Для депозитов проверяем, что средства ещё есть
                if (bccomp($wallet->balance, $transaction->amount, self::SCALE) < 0) {
                    throw new \RuntimeException(
                        "Cannot cancel {$transaction->type}: insufficient balance. " .
                        "Need: {$transaction->amount}, Have: {$wallet->balance}"
                    );
                }
                $newBalance = bcsub($wallet->balance, $transaction->amount, self::SCALE);
            } else {
                // Транзакция уменьшала баланс (withdraw/fee) → восстанавливаем
                $newBalance = bcadd($wallet->balance, $transaction->amount, self::SCALE);
            }

            $wallet->balance = $newBalance;
            $wallet->save();

            $transaction->status = 'cancelled';
            $transaction->save();

            Log::info('Transaction cancelled', [
                'transaction_id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'new_balance' => $newBalance
            ]);
        });
    }

    /**
     * Создание транзакции и обновление баланса.
     */
    private function createTransaction(Wallet $wallet, TransactionTypeEnum $type, string $amount, ?string $txid, array $metadata): CryptoTransaction
    {
        return DB::transaction(function () use ($wallet, $type, $amount, $txid, $metadata) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $balanceBefore = $wallet->balance;

            // Проверка достаточности для дебетовых операций
            if (!$type->isCredit() && bccomp($balanceBefore, $amount, self::SCALE) < 0) {
                throw new RuntimeException('Insufficient balance for ' . $type->value);
            }

            $balanceAfter = $type->isCredit()
                ? bcadd($balanceBefore, $amount, self::SCALE)
                : bcsub($balanceBefore, $amount, self::SCALE);

            $transaction = CryptoTransaction::create([
                'wallet_id'      => $wallet->id,
                'txid'           => $txid,
                'type'           => $type->value,
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'status'         => 'pending',
                'metadata'       => $metadata,
            ]);

            $wallet->balance = $balanceAfter;
            $wallet->save();

            return $transaction;
        });
    }
}
