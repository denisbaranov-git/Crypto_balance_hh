<?php

namespace App\Services\Withdrawal;

use App\Contracts\WithdrawalStrategy;
use InvalidArgumentException;

class WithdrawalStrategyFactory
{
    /** @var WithdrawalStrategy[] */
    private array $strategies = [];

    public function addStrategy(WithdrawalStrategy $strategy): void
    {
        $this->strategies[] = $strategy;
    }

    public function getStrategyForCurrency(string $currency): WithdrawalStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($currency)) {
                return $strategy;
            }
        }

        throw new InvalidArgumentException("No withdrawal strategy found for currency: {$currency}");
    }
}
