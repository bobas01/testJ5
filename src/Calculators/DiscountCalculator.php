<?php

namespace OrderReport\Calculators;

use OrderReport\OrderConfig;

class DiscountCalculator
{
    public static function calculateVolumeDiscount(float $subtotal, string $level): float
    {
        $discount = 0.0;
        
        if ($subtotal > 50) {
            $discount = $subtotal * 0.05;
        }
        if ($subtotal > 100) {
            $discount = $subtotal * 0.10;
        }
        if ($subtotal > 500) {
            $discount = $subtotal * 0.15;
        }
        if ($subtotal > 1000 && $level === 'PREMIUM') {
            $discount = $subtotal * 0.20;
        }
        
        return $discount;
    }

    public static function applyWeekendBonus(float $discount, string $firstOrderDate): float
    {
        if (empty($firstOrderDate)) {
            return $discount;
        }
        
        $timestamp = strtotime($firstOrderDate);
        if ($timestamp === false) {
            return $discount;
        }
        
        $dayOfWeek = intval(date('w', $timestamp));
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            $discount = $discount * 1.05;
        }
        
        return $discount;
    }

    public static function calculateLoyaltyDiscount(float $loyaltyPoints): float
    {
        $discount = 0.0;
        
        if ($loyaltyPoints > 100) {
            $discount = min($loyaltyPoints * 0.1, 50.0);
        }
        if ($loyaltyPoints > 500) {
            $discount = min($loyaltyPoints * 0.15, 100.0);
        }
        
        return $discount;
    }

    public static function applyMaxDiscountCap(float $volumeDiscount, float $loyaltyDiscount): array
    {
        $totalDiscount = $volumeDiscount + $loyaltyDiscount;
        
        if ($totalDiscount > OrderConfig::MAX_DISCOUNT) {
            $ratio = OrderConfig::MAX_DISCOUNT / $totalDiscount;
            $volumeDiscount = $volumeDiscount * $ratio;
            $loyaltyDiscount = $loyaltyDiscount * $ratio;
            $totalDiscount = OrderConfig::MAX_DISCOUNT;
        }
        
        return [
            'volume' => $volumeDiscount,
            'loyalty' => $loyaltyDiscount,
            'total' => $totalDiscount
        ];
    }
}

