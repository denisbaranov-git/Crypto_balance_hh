<?php

namespace App\Enums;

enum TransactionTypeEnum: string
{
    case DEPOSIT  = 'deposit';
    case WITHDRAW = 'withdraw';
    case FEE      = 'fee';
    case REFUND   = 'refund';
}
