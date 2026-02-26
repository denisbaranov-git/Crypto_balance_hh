<?php

namespace App\Models;

use App\Enums\TransactionTypeEnum;
use Illuminate\Database\Eloquent\Model;

class CryptoTransaction extends Model
{
    protected $fillabel = [
        'wallet_id', 'txid', 'type', 'amount', 'balance_before',
        'balance_after', 'status', 'metadata'
    ];
//    protected $casts = [
//        'amount' => 'decimal:8',
//        'balance_before' => 'decimal:8',
//        'balance_after' => 'decimal:8',
//        'type' => TransactionTypeEnum::class,
//        'metadata' => 'array'
//    ];
    protected $casts = [
        'amount' => 'string',
        'balance_before' => 'string',
        'balance_after' => 'string',
        'type' => TransactionTypeEnum::class,
        'metadata' => 'array'
    ];
//'balance' => 'string',

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
    public function isConfirmed(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Транзакция ожидает подтверждения.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
