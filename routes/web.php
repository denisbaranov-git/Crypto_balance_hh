<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\WalletController;
use App\Http\Controllers\Web\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Аутентификация (если не используется Laravel Breeze)
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Защищённые маршруты
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Кошельки
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/{currency}', [WalletController::class, 'show'])->name('show');
        Route::get('/{currency}/withdraw', [WalletController::class, 'withdrawForm'])->name('withdraw.form');
        Route::post('/{currency}/withdraw', [WalletController::class, 'withdraw'])->name('withdraw');
    });

    // Транзакции
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transaction.show');
});
