<?php

namespace OrderReport\Formatters;

class ReportFormatter
{
    public static function formatCustomerReport(
        string $name,
        string $customerId,
        string $level,
        string $zone,
        string $currency,
        float $subtotal,
        float $totalDiscount,
        float $volumeDiscount,
        float $loyaltyDiscount,
        float $morningBonus,
        float $tax,
        float $taxCurrencyRate,
        string $zoneName,
        float $weight,
        float $shipping,
        float $handling,
        int $itemCount,
        float $total,
        int $loyaltyPoints
    ): array {
        $lines = [];
        
        $lines[] = sprintf('Customer: %s (%s)', $name, $customerId);
        $lines[] = sprintf('Level: %s | Zone: %s | Currency: %s', $level, $zone, $currency);
        $lines[] = sprintf('Subtotal: %.2f', $subtotal);
        $lines[] = sprintf('Discount: %.2f', $totalDiscount);
        $lines[] = sprintf('  - Volume discount: %.2f', $volumeDiscount);
        $lines[] = sprintf('  - Loyalty discount: %.2f', $loyaltyDiscount);
        
        if ($morningBonus > 0) {
            $lines[] = sprintf('  - Morning bonus: %.2f', $morningBonus);
        }
        
        $lines[] = sprintf('Tax: %.2f', $tax * $taxCurrencyRate);
        $lines[] = sprintf('Shipping (%s, %.1fkg): %.2f', $zoneName, $weight, $shipping);
        
        if ($handling > 0) {
            $lines[] = sprintf('Handling (%d items): %.2f', $itemCount, $handling);
        }
        
        $lines[] = sprintf('Total: %.2f %s', $total, $currency);
        $lines[] = sprintf('Loyalty Points: %d', $loyaltyPoints);
        $lines[] = '';
        
        return $lines;
    }

    public static function formatSummary(float $grandTotal, float $totalTaxCollected): array
    {
        return [
            sprintf('Grand Total: %.2f EUR', $grandTotal),
            sprintf('Total Tax Collected: %.2f EUR', $totalTaxCollected)
        ];
    }

    public static function formatReport(array $customerReports, array $summary): string
    {
        $allLines = [];
        foreach ($customerReports as $report) {
            $allLines = array_merge($allLines, $report);
        }
        $allLines = array_merge($allLines, $summary);
        return implode("\n", $allLines);
    }
}

