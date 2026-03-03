<?php
// app/Http/Controllers/Api/WalletController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWithdrawalJob;
use App\Rules\SupportedNetwork;
use App\Services\AccountService;
use App\Services\Wallet\WalletCreationService;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function __construct(
        private AccountService $accountService,
        private WalletCreationService $walletCreator
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Получить или создать кошелёк.
     */
    public function show(Request $request)
    {
        $request->validate(['currency' => 'required|string']);

        $user = Auth::user();
        $wallet = $user->wallets()->where('currency', $request->currency)->first();

        if (!$wallet) {
            $wallet = $this->walletCreator->createWallet($user, $request->currency);
        }

        return response()->json([
            'currency' => $wallet->currency,
            'address' => $wallet->address,
            'balance' => $wallet->balance,
        ]);
    }

    /**
     * Запрос на вывод.
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'currency' => 'required|string',
            'network' => ['required|string',new SupportedNetwork], //Rule::in(array_keys(config('networks'))),
            'amount' => 'required|string',
            'to_address' => 'required|string',
        ]);

        $wallet = Auth::user()->wallets()
            ->where('currency', $request->currency)
            ->where('network',$request->network)
            ->firstOrFail();

        try {
            $transaction = $this->accountService->withdraw(
                $wallet,
                $request->amount,
                ['to' => $request->to_address]
            );

            SendWithdrawalJob::dispatch($transaction);

            return response()->json([
                'message' => 'Withdrawal created',
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
