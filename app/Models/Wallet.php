<?php

namespace App\Models;

use App\Services\TokenConfigService;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'currency',
        'balance',
        //'address',
        'decimals',
        'last_scanned_block',
        'last_scanned_block_hash',
    ];
    protected $casts = [
        'balance' => 'string', // Храним как строку для точности BCMath
        'decimals' => 'integer',
        'last_scanned_block' => 'integer',
        'last_scanned_block_hash' => 'string',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cryptoTransactions()
    {
        return $this->hasMany(CryptoTransaction::class);
    }

    public function needsScanning(int $currentBlock, int $threshold = 100): bool
    {
        return ($this->last_scanned_block ?? 0) < $currentBlock - $threshold;
    }
    public function getTokenConfigAttribute(): ?array
    {
        return app(TokenConfigService::class)->getTokenNetworkConfig(
            $this->currency,
            $this->network
        );
    }
    public function networkKey()
    {
        return $this->belongsTo(UserNetworkKey::class, 'network', 'network')
            ->where('user_id', $this->user_id);
    }

    public function getPrivateKeyAttribute()
    {
        return $this->networkKey?->encrypted_private_key;
    }

    public function getAddressAttribute(): ?string
    {
        return $this->networkKey?->address;
    }
}
