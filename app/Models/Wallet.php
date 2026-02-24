<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'currency', 'balance', 'address'];

    protected $casts = [
       // 'balance' => 'decimal:8',
        'balance' => 'string',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cryptoTransactions()
    {
        return $this->hasMany(CryptoTransaction::class);
    }
}
