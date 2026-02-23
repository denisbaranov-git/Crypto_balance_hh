<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillabel = [
        'wallet_id', 'txid', 'type', 'amount', 'balance_before',
        'balance_after', 'status', 'metadata'
    ];
    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'metadata' => 'array'
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
