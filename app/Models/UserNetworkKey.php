<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNetworkKey extends Model
{
    protected $fillable = ['user_id', 'network', 'encrypted_private_key', 'address'];

    protected $casts = [
        'encrypted_private_key' => 'encrypted', // если используется шифрование Laravel
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class, 'network', 'network')
            ->where('user_id', $this->user_id);
    }
}
