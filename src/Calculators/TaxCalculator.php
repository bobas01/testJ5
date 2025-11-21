<?php

namespace OrderReport\Calculators;

use OrderReport\OrderConfig;
use OrderReport\Models\Order;
use OrderReport\Models\Product;

class TaxCalculator
{
    public static function calculateTax(
        float $taxableAmount,
        array $items,
        array $products
    ): float {
        $allTaxable = self::areAllItemsTaxable($items, $products);
        
        if ($allTaxable) {
            return round($taxableAmount * OrderConfig::TAX, 2);
        }
        
        return self::calculateTaxPerItem($items, $products);
    }

    private static function areAllItemsTaxable(array $items, array $products): bool
    {
        foreach ($items as $item) {
            $prod = $products[$item->productId] ?? null;
            if ($prod && $prod->taxable === false) {
                return false;
            }
        }
        return true;
    }

    private static function calculateTaxPerItem(array $items, array $products): float
    {
        $tax = 0.0;
        
        foreach ($items as $item) {
            $prod = $products[$item->productId] ?? null;
            if ($prod && $prod->taxable !== false) {
                $itemTotal = $item->qty * ($prod->price ?? $item->unitPrice);
                $tax += $itemTotal * OrderConfig::TAX;
            }
        }
        
        return round($tax, 2);
    }
}

