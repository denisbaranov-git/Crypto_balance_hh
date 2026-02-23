<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crypto_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->string('currency', 20); // usdt, btc, eth
            $table->string('network', 20); // trc20, erc20, bep20
            $table->enum('type', ['deposit', 'withdraw', 'fee', 'refund']);
            $table->enum('status', ['pending', 'completed', 'failed', 'canceled'])->default('pending');
            $table->string('txid', 255)->nullable()->unique(); // ID транзакции в блокчейне
            $table->string('from_address', 255)->nullable();
            $table->string('to_address', 255)->nullable();

//            $table->decimal('amount', 30, 18)->default(0);
//            $table->decimal('fee', 30, 18)->default(0);// Комиссия сети, которую заплатили (Gas fee)
//            $table->decimal('balance_before', 30, 18)->default(0);
//            $table->decimal('balance_after', 30, 18)->default(0);

            $table->string('amount', 64)->default('0');
            $table->string('fee', 64)->default('0');
            $table->string('balance_before', 64)->default('0');
            $table->string('balance_after', 64)->default('0');

            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('txid');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_transactions');
    }
};
