<?php

namespace App\Enums;

enum TransactionTypeEnum: string
{
    case DEPOSIT  = 'deposit';
    case WITHDRAW = 'withdraw';
    case FEE      = 'fee';
    case REFUND   = 'refund';

    public function isCredit(): bool //true //balans +
    {
        return match($this) {
            self::DEPOSIT, self::REFUND => true, //balans +
            self::WITHDRAW, self::FEE   => false,//balans -  is debet
        };
    }
}
