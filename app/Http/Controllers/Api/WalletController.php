<?php
// app/Http/Controllers/Api/WalletController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\WalletCreationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Получить или создать кошелек для указанной валюты.
     */
    public function getWallet(Request $request, WalletCreationService $walletCreationService)
    {
        $data = $request->validate(['currency' => 'required|in:ETH,BTC']);

        $user = Auth::user();
        $wallet = $walletCreationService->createWallet($user, $data['currency']);

//        $wallet = Wallet::firstOrCreate(
//            ['user_id' => $user->id, 'currency' => $request->currency],
//            ['address' => $this->generateAddress()] // генерация адреса (можно через клиент)
//        );

        return response()->json([
            'currency' => $wallet->currency,
            'balance' => $wallet->balance,
            'address' => $wallet->address,
        ]);
    }

    /**
     * Создать запрос на вывод средств.
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'currency' => 'required|in:ETH,BTC',
            'amount' => 'required|numeric|min:0.00000001',
            'to_address' => 'required|string',
        ]);

        $user = Auth::user();
        $wallet = Wallet::where('user_id', $user->id)->where('currency', $request->currency)->firstOrFail();

        // Используем сервис для списания
        $accountService = app(\App\Services\CryptoService::class);
        $transaction = $accountService->debit($wallet, $request->amount, [
            'to' => $request->to_address,
            'note' => $request->input('note'),
        ]);

        // Здесь можно отправить задачу на реальную отправку транзакции в блокчейн
        // SendWithdrawalJob::dispatch($transaction);

        return response()->json([
            'message' => 'Withdrawal request created',
            'transaction_id' => $transaction->id,
            'status' => $transaction->status,
        ]);
    }

    private function generateAddress( ): string
    {

        // В реальности использовать генерацию адреса через клиент ноды
        return '0x' . bin2hex(random_bytes(20));
    }
}
