<?php

namespace OrderReport;

require_once __DIR__ . '/CsvReader.php';
require_once __DIR__ . '/Calculators/DiscountCalculator.php';
require_once __DIR__ . '/Calculators/TaxCalculator.php';
require_once __DIR__ . '/Calculators/ShippingCalculator.php';
require_once __DIR__ . '/Calculators/CurrencyConverter.php';
require_once __DIR__ . '/Formatters/ReportFormatter.php';
require_once __DIR__ . '/IO/ReportWriter.php';

use OrderReport\Calculators\DiscountCalculator;
use OrderReport\Calculators\TaxCalculator;
use OrderReport\Calculators\ShippingCalculator;
use OrderReport\Calculators\CurrencyConverter;
use OrderReport\Formatters\ReportFormatter;
use OrderReport\IO\ReportWriter;

class OrderConfig
{
    public const TAX = 0.2;
    public const SHIPPING_LIMIT = 50.0;
    public const SHIP = 5.0;
    public const PREMIUM_THRESHOLD = 1000.0;
    public const LOYALTY_RATIO = 0.01;
    public const HANDLING_FEE = 2.5;
    public const MAX_DISCOUNT = 200.0;
}


function run()
{
    $base = __DIR__;
    $custPath = $base . '/data/customers.csv';
    $ordPath = $base . '/data/orders.csv';
    $prodPath = $base . '/data/products.csv';
    $shipPath = $base . '/data/shipping_zones.csv';
    $promoPath = $base . '/data/promotions.csv';

    $customers = CsvReader::readCustomers($custPath);
    $products = CsvReader::readProducts($prodPath);
    $shippingZones = CsvReader::readShippingZones($shipPath);
    $promotions = CsvReader::readPromotions($promoPath);
    $orders = CsvReader::readOrders($ordPath);

    // Calcul points de fidélité (première duplication)
    $loyaltyPoints = [];
    foreach ($orders as $o) {
        $cid = $o->customerId;
        if (!isset($loyaltyPoints[$cid])) {
            $loyaltyPoints[$cid] = 0;
        }
        // Calcul basé sur prix commande
        $loyaltyPoints[$cid] += $o->qty * $o->unitPrice * OrderConfig::LOYALTY_RATIO;
    }

    // Groupement par client (logique métier mélangée)
    $totalsByCustomer = [];
    foreach ($orders as $o) {
        $cid = $o->customerId;

        // Récupération produit avec fallback
        $prod = $products[$o->productId] ?? null;
        $basePrice = $prod?->price ?? $o->unitPrice;

        // Application promo (logique complexe et bugguée)
        $promoCode = $o->promoCode;
        $discountRate = 0;
        $fixedDiscount = 0;

        if (!empty($promoCode) && isset($promotions[$promoCode])) {
            $promo = $promotions[$promoCode];
            if ($promo->active) {
                if ($promo->type === 'PERCENTAGE') {
                    $discountRate = floatval($promo->value) / 100;
                } elseif ($promo->type === 'FIXED') {
                    // Bug: appliqué par ligne au lieu de global
                    $fixedDiscount = floatval($promo->value);
                }
            }
        }

        // Calcul ligne avec réduction promo
        $lineTotal = $o->qty * $basePrice * (1 - $discountRate) - $fixedDiscount * $o->qty;

        // Bonus matin (règle cachée basée sur heure)
        $hour = intval(explode(':', $o->time)[0]);
        $morningBonus = 0;
        if ($hour < 10) {
            $morningBonus = $lineTotal * 0.03; // 3% réduction supplémentaire
        }
        $lineTotal = $lineTotal - $morningBonus;

        if (!isset($totalsByCustomer[$cid])) {
            $totalsByCustomer[$cid] = [
                'subtotal' => 0.0,
                'items' => [],
                'weight' => 0.0,
                'promoDiscount' => 0.0,
                'morningBonus' => 0.0
            ];
        }

        $totalsByCustomer[$cid]['subtotal'] += $lineTotal;
        $totalsByCustomer[$cid]['weight'] += ($prod?->weight ?? 1.0) * $o->qty;
        $totalsByCustomer[$cid]['items'][] = $o;
        $totalsByCustomer[$cid]['morningBonus'] += $morningBonus;
    }

    $customerReports = [];
    $jsonData = [];
    $grandTotal = 0.0;
    $totalTaxCollected = 0.0;

    $sortedCustomerIds = array_keys($totalsByCustomer);
    sort($sortedCustomerIds);

    foreach ($sortedCustomerIds as $cid) {
        $cust = $customers[$cid] ?? null;
        $name = $cust?->name ?? 'Unknown';
        $level = $cust?->level ?? 'BASIC';
        $zone = $cust?->shippingZone ?? 'ZONE1';
        $currency = $cust?->currency ?? 'EUR';

        $sub = $totalsByCustomer[$cid]['subtotal'];

        $pts = $loyaltyPoints[$cid] ?? 0;
        $firstOrderDate = $totalsByCustomer[$cid]['items'][0]->date ?? '';

        $volumeDiscount = DiscountCalculator::calculateVolumeDiscount($sub, $level);
        $volumeDiscount = DiscountCalculator::applyWeekendBonus($volumeDiscount, $firstOrderDate);
        $loyaltyDiscount = DiscountCalculator::calculateLoyaltyDiscount($pts);

        $discounts = DiscountCalculator::applyMaxDiscountCap($volumeDiscount, $loyaltyDiscount);
        $disc = $discounts['volume'];
        $loyaltyDiscount = $discounts['loyalty'];
        $totalDiscount = $discounts['total'];

        $taxable = $sub - $totalDiscount;
        $tax = TaxCalculator::calculateTax(
            $taxable,
            $totalsByCustomer[$cid]['items'],
            $products
        );

        $weight = $totalsByCustomer[$cid]['weight'];
        $ship = ShippingCalculator::calculateShipping($sub, $weight, $zone, $shippingZones);

        $itemCount = count($totalsByCustomer[$cid]['items']);
        $handling = ShippingCalculator::calculateHandling($itemCount);

        $currencyRate = CurrencyConverter::getRate($currency);

        $total = round(($taxable + $tax + $ship + $handling) * $currencyRate, 2);
        $grandTotal += $total;
        $totalTaxCollected += $tax * $currencyRate;

        $customerReports[] = ReportFormatter::formatCustomerReport(
            $name,
            $cid,
            $level,
            $zone,
            $currency,
            $sub,
            $totalDiscount,
            $disc,
            $loyaltyDiscount,
            $totalsByCustomer[$cid]['morningBonus'],
            $tax,
            $currencyRate,
            $zone,
            $weight,
            $ship,
            $handling,
            $itemCount,
            $total,
            floor($pts)
        );

        $jsonData[] = [
            'customer_id' => $cid,
            'name' => $name,
            'total' => $total,
            'currency' => $currency,
            'loyalty_points' => floor($pts)
        ];
    }

    $summary = ReportFormatter::formatSummary($grandTotal, $totalTaxCollected);
    $result = ReportFormatter::formatReport($customerReports, $summary);

    ReportWriter::writeToConsole($result);

    $outputPath = $base . '/output.json';
    ReportWriter::writeJsonToFile($outputPath, $jsonData);

    return $result;
}

// Point d'entrée
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    run();
}
