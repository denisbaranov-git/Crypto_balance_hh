<?php
// app/Http/Controllers/Web/DashboardController.php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\User;

use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Получаем кошельки пользователя (можно создать, если их нет)
//        $currencies = ['USDT', 'ETH'];
//        foreach($currencies as $currency) {
//            Wallet::firstOrCreate(['user_id' => $user->id, 'currency' => $currency], ['address' => ($currency)]);
//        }
//        $wallets = Wallet::where(['user_id' => $user->id])->get();

        $wallets = $user->wallets()->get();


        //common balance in USD
        $totalBalanceUsd = $this->calculateTotalBalance($wallets);

        return view('dashboard.index', [
            'user' => $user,
            'wallets' => $wallets,
            'totalBalanceUsd' => $totalBalanceUsd,
        ]);
    }

    private function calculateTotalBalance($wallets): float //common balance in USD
    {
        // Здесь обращение к сервису курсов валют и подсчёт суммы
        return 0;
    }
}
