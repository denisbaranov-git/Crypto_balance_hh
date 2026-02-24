<?php

use App\Enums\TransactionTypeEnum;
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
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->string('txid')->unique()->nullable(); // хеш транзакции в блокчейне
            $table->unsignedBigInteger('block_number')->nullable();
            //$table->enum('type', ['deposit', 'withdraw', 'fee', 'refund']);
            $table->enum('type', array_column(TransactionTypeEnum::cases(), 'value'));
            $table->decimal('amount', 20, 8);
            $table->decimal('balance_before', 20, 8);
            $table->decimal('balance_after', 20, 8);
            $table->string('status')->default('pending'); // pending, completed, failed, cancelled
            $table->text('metadata')->nullable(); // дополнительная информация (адрес отправки, комиссия и т.п.)
            $table->timestamps();

            $table->index('block_number');
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
