<?php

namespace OrderReport;

require_once __DIR__ . '/CsvReader.php';
require_once __DIR__ . '/Calculators/DiscountCalculator.php';

use OrderReport\Calculators\DiscountCalculator;

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

    // Génération rapport (mélange calculs + formatage + I/O)
    $outputLines = [];
    $jsonData = [];
    $grandTotal = 0.0;
    $totalTaxCollected = 0.0;

    // Tri par ID client (comportement à préserver)
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

        // Calcul taxe (gestion spéciale par produit)
        $taxable = $sub - $totalDiscount;
        $tax = 0.0;

        // Vérifier si tous produits taxables
        $allTaxable = true;
        foreach ($totalsByCustomer[$cid]['items'] as $item) {
            $prod = $products[$item->productId] ?? null;
            if ($prod && $prod->taxable === false) {
                $allTaxable = false;
                break;
            }
        }

        if ($allTaxable) {
            $tax = round($taxable * OrderConfig::TAX, 2); // Arrondi 2 décimales
        } else {
            // Calcul taxe par ligne (plus complexe)
            foreach ($totalsByCustomer[$cid]['items'] as $item) {
                $prod = $products[$item->productId] ?? null;
                if ($prod && $prod->taxable !== false) {
                    $itemTotal = $item->qty * ($prod->price ?? $item->unitPrice);
                    $tax += $itemTotal * OrderConfig::TAX;
                }
            }
            $tax = round($tax, 2);
        }

        // Frais de port complexes (duplication)
        $ship = 0.0;
        $weight = $totalsByCustomer[$cid]['weight'];

        if ($sub < OrderConfig::SHIPPING_LIMIT) {
            $shipZone = $shippingZones[$zone] ?? new \OrderReport\Models\ShippingZone(zone: $zone, base: 5.0, perKg: 0.5);
            $baseShip = $shipZone->base;

            if ($weight > 10) {
                $ship = $baseShip + ($weight - 10) * $shipZone->perKg;
            } elseif ($weight > 5) {
                // Palier intermédiaire (règle cachée)
                $ship = $baseShip + ($weight - 5) * 0.3;
            } else {
                $ship = $baseShip;
            }

            // Majoration zones éloignées
            if ($zone === 'ZONE3' || $zone === 'ZONE4') {
                $ship = $ship * 1.2;
            }
        } else {
            // Livraison gratuite mais frais manutention poids élevé
            if ($weight > 20) {
                $ship = ($weight - 20) * 0.25;
            }
        }

        // Frais de gestion (magic number + condition cachée)
        $handling = 0.0;
        $itemCount = count($totalsByCustomer[$cid]['items']);
        if ($itemCount > 10) {
            $handling = OrderConfig::HANDLING_FEE;
        }
        if ($itemCount > 20) {
            $handling = OrderConfig::HANDLING_FEE * 2; // double pour grosses commandes
        }

        // Conversion devise (règle cachée pour non-EUR)
        $currencyRate = 1.0;
        if ($currency === 'USD') {
            $currencyRate = 1.1;
        } elseif ($currency === 'GBP') {
            $currencyRate = 0.85;
        }

        $total = round(($taxable + $tax + $ship + $handling) * $currencyRate, 2);
        $grandTotal += $total;
        $totalTaxCollected += $tax * $currencyRate;

        // Formatage texte (dispersé, pas de fonction dédiée)
        $outputLines[] = sprintf('Customer: %s (%s)', $name, $cid);
        $outputLines[] = sprintf('Level: %s | Zone: %s | Currency: %s', $level, $zone, $currency);
        $outputLines[] = sprintf('Subtotal: %.2f', $sub);
        $outputLines[] = sprintf('Discount: %.2f', $totalDiscount);
        $outputLines[] = sprintf('  - Volume discount: %.2f', $disc);
        $outputLines[] = sprintf('  - Loyalty discount: %.2f', $loyaltyDiscount);
        if ($totalsByCustomer[$cid]['morningBonus'] > 0) {
            $outputLines[] = sprintf('  - Morning bonus: %.2f', $totalsByCustomer[$cid]['morningBonus']);
        }
        $outputLines[] = sprintf('Tax: %.2f', $tax * $currencyRate);
        $outputLines[] = sprintf('Shipping (%s, %.1fkg): %.2f', $zone, $weight, $ship);
        if ($handling > 0) {
            $outputLines[] = sprintf('Handling (%d items): %.2f', $itemCount, $handling);
        }
        $outputLines[] = sprintf('Total: %.2f %s', $total, $currency);
        $outputLines[] = sprintf('Loyalty Points: %d', floor($pts));
        $outputLines[] = '';

        // Export JSON en parallèle (side effect)
        $jsonData[] = [
            'customer_id' => $cid,
            'name' => $name,
            'total' => $total,
            'currency' => $currency,
            'loyalty_points' => floor($pts)
        ];
    }

    $outputLines[] = sprintf('Grand Total: %.2f EUR', $grandTotal);
    $outputLines[] = sprintf('Total Tax Collected: %.2f EUR', $totalTaxCollected);

    $result = implode("\n", $outputLines);

    // Side effects: echo + file write
    echo $result;

    // Export JSON surprise
    $outputPath = $base . '/output.json';
    file_put_contents($outputPath, json_encode($jsonData, JSON_PRETTY_PRINT));

    return $result;
}

// Point d'entrée
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    run();
}
