<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AccountService;
use App\Services\Wallet\WalletCreationService;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class WalletController extends Controller
{
    public function __construct(
        private AccountService $accountService,
        private WalletCreationService $walletCreator
    ) {}

    /**
     * Показать страницу кошелька.
     */
    public function show(string $currency)
    {
        $user = Auth::user();

        $wallet = $user->wallets()->where('currency', $currency)->first();

        if (!$wallet) {
            // Если кошелька нет, создаём
            $wallet = $this->walletCreator->createWallet($user, $currency);
        }

        return view('wallet.show', [
            'wallet' => $wallet,
            'recentTransactions' => $wallet->transactions()
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Форма вывода.
     */
    public function withdrawForm(string $currency)
    {
        $wallet = Auth::user()->wallets()->where('currency', $currency)->firstOrFail();
        return view('wallet.withdraw', ['wallet' => $wallet]);
    }

    /**
     * Обработка вывода.
     */
    public function withdraw(Request $request, string $currency)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.000001',
            'to_address' => 'required|string',
        ]);

        $wallet = Auth::user()->wallets()->where('currency', $currency)->firstOrFail();

        try {
            $transaction = $this->accountService->withdraw(
                $wallet,
                $request->amount,
                ['to' => $request->to_address]
            );

            // Отправляем задачу на реальную отправку в блокчейн
            SendWithdrawalJob::dispatch($transaction);

            return redirect()->route('wallet.show', $currency)
                ->with('success', 'Withdrawal request created successfully');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }
    }
}
