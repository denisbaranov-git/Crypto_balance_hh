<?php
// app/Services/CryptoService.php

namespace App\Services;

use App\Models\Wallet;
use App\Models\CryptoTransaction;
use App\Enums\TransactionTypeEnum;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CryptoService
{
    // Точность вычислений (количество знаков после запятой) – должна совпадать с точностью хранения в БД.
    private const SCALE = 8;

    public function deposit(Wallet $wallet, string $amount, ?string $txid = null, array $metadata = []): CryptoTransaction
    {
        return $this->createTransaction($wallet, TransactionTypeEnum::DEPOSIT, $amount, $txid, $metadata);
    }

    public function withdraw(Wallet $wallet, string $amount, array $metadata = []): CryptoTransaction
    {
        return $this->createTransaction($wallet, TransactionTypeEnum::WITHDRAW, $amount, null, $metadata);
    }

    public function fee(Wallet $wallet, string $amount, ?string $txid = null, array $metadata = []): CryptoTransaction
    {
        return $this->createTransaction($wallet, TransactionTypeEnum::FEE, $amount, $txid, $metadata);
    }

    public function refund(Wallet $wallet, string $amount, ?string $txid = null, array $metadata = []): CryptoTransaction
    {
        return $this->createTransaction($wallet, TransactionTypeEnum::REFUND, $amount, $txid, $metadata);
    }

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

    public function cancelTransaction(CryptoTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $transaction = CryptoTransaction::where('id', $transaction->id)->lockForUpdate()->first();
            if ($transaction->status !== 'pending') return;

            $wallet = Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->first();

            // Восстанавливаем баланс в зависимости от типа
            if (in_array($transaction->type, [TransactionTypeEnum::WITHDRAW, TransactionTypeEnum::FEE])) {
                // При списании баланс был уменьшен, значит возвращаем
                $wallet->balance = bcadd($wallet->balance, $transaction->amount, self::SCALE);
            } elseif (in_array($transaction->type, [TransactionTypeEnum::DEPOSIT, TransactionTypeEnum::REFUND])) {
                // При зачислении баланс был увеличен, значит уменьшаем
                $wallet->balance = bcsub($wallet->balance, $transaction->amount, self::SCALE);
            }

            $wallet->save();
            $transaction->status = 'cancelled';
            $transaction->save();
        });
    }

    private function createTransaction(Wallet $wallet, TransactionTypeEnum $type, string $amount, ?string $txid, array $metadata): CryptoTransaction
    {
        $isCredit = in_array($type, [
            TransactionTypeEnum::DEPOSIT,
            TransactionTypeEnum::REFUND,
        ], true);

        return DB::transaction(function () use ($wallet, $type, $amount, $txid, $metadata, $isCredit) {
            // Блокируем кошелёк
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $balanceBefore = $wallet->balance; // строка

            // Проверка достаточности для списания
            if (!$isCredit && bccomp($balanceBefore, $amount, self::SCALE) < 0) {
                throw new RuntimeException('Insufficient balance for ' . $type->value);
            }

            $balanceAfter = $isCredit
                ? bcadd($balanceBefore, $amount, self::SCALE)
                : bcsub($balanceBefore, $amount, self::SCALE);

            $transaction = CryptoTransaction::create([
                'wallet_id'      => $wallet->id,
                'txid'           => $txid,
                'block_number' => $metadata['block'] ?? null,
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
    private function rollbackTransactionsFrom(int $startBlock, CryptoService $accountService): void
    {
        $transactions = CryptoTransaction::where('status', 'pending')
            ->where('block_number', '>=', $startBlock)
            ->get();

        foreach ($transactions as $transaction) {
            $accountService->cancelTransaction($transaction);
        }
    }
}
