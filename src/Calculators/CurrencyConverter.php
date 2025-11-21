<?php

namespace OrderReport\Calculators;

class CurrencyConverter
{
    public static function getRate(string $currency): float
    {
        return match ($currency) {
            'USD' => 1.1,
            'GBP' => 0.85,
            default => 1.0
        };
    }
}

