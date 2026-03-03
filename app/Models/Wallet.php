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

    //$threshold	Частота сканирования	Риск пропустить транзакцию
    //1	Каждый блок (12 сек)	            Минимальный	Огромная нагрузка
    //10	        ~2 минуты	            Низкий	Приемлемо
    //100       	~20 минут	            Средний	Экономично
    //1000      	~3.5 часа	            Высокий	Очень экономично
    //100 блоков в Ethereum ≈ 20 минут (12 секунд на блок × 100)
    // need progressive scanning that depend balance and activities of user (last_activity) //denis
    public function needsScanning(int $currentBlock, int $threshold = 100): bool
    {
        // Для активных пользователей — малый порог
        if ($this->user->last_activity_at > now()->subHour()) {
            return ($this->last_scanned_block ?? 0) < $currentBlock - 10;
        }

        // 2. Дополнительная проверка: если прошло больше 2 часов, сканируем независимо от порога
        $timeSinceLastScan = now()->diffInHours($this->updated_at);

        if ($timeSinceLastScan > 2) {
            return true; // сканируем по времени, а не по блокам
        }

        return ($this->last_scanned_block ?? 0) < $currentBlock - $threshold;
    }

//    public function needsScanning(int $currentBlock): bool
//    {
//        $threshold = $this->getDynamicThreshold();
//        return ($this->last_scanned_block ?? 0) < $currentBlock - $threshold;
//    }
//
//    private function getDynamicThreshold(): int
//    {
//        if ($this->balance > 10000) return 10; // крупные суммы — чаще
//        if ($this->user->last_login_at > now()->subDay()) return 50; // активные — чаще
//        return 100; // обычные пользователи
//    }
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
