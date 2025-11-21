<?php

// Constantes globales mal organisées
define('TAX', 0.2);
define('SHIPPING_LIMIT', 50);
define('SHIP', 5.0);
define('PREMIUM_THRESHOLD', 1000);
define('LOYALTY_RATIO', 0.01);
define('HANDLING_FEE', 2.5);
define('MAX_DISCOUNT', 200);

// Fonction principale qui fait TOUT (250+ lignes)
function run()
{
    $base = __DIR__;
    $custPath = $base . '/data/customers.csv';
    $ordPath = $base . '/data/orders.csv';
    $prodPath = $base . '/data/products.csv';
    $shipPath = $base . '/data/shipping_zones.csv';
    $promoPath = $base . '/data/promotions.csv';

    // Lecture customers (parsing mélangé avec logique)
    $customers = [];
    $custFile = fopen($custPath, 'r');
    $header = fgetcsv($custFile); // skip header
    while (($row = fgetcsv($custFile)) !== false) {
        $customers[$row[0]] = [
            'id' => $row[0],
            'name' => $row[1],
            'level' => $row[2] ?? 'BASIC',
            'shipping_zone' => $row[3] ?? 'ZONE1',
            'currency' => $row[4] ?? 'EUR'
        ];
    }
    fclose($custFile);

    // Lecture products (duplication du parsing, méthode différente)
    $products = [];
    if (($handle = fopen($prodPath, 'r')) !== false) {
        $headers = fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== false) {
            try {
                $products[$data[0]] = [
                    'id' => $data[0],
                    'name' => $data[1],
                    'category' => $data[2],
                    'price' => floatval($data[3]),
                    'weight' => floatval($data[4] ?? 1.0),
                    'taxable' => ($data[5] ?? 'true') === 'true'
                ];
            } catch (Exception $e) {
                // Skip silencieux
                continue;
            }
        }
        fclose($handle);
    }

    // Lecture shipping zones (encore une autre variation)
    $shippingZones = [];
    $shipContent = file_get_contents($shipPath);
    $shipLines = explode("\n", $shipContent);
    array_shift($shipLines); // remove header
    foreach ($shipLines as $line) {
        if (trim($line) === '') continue;
        $p = str_getcsv($line);
        $shippingZones[$p[0]] = [
            'zone' => $p[0],
            'base' => floatval($p[1]),
            'per_kg' => floatval($p[2] ?? 0.5)
        ];
    }

    // Lecture promotions (parsing avec @ pour supprimer warnings)
    $promotions = [];
    if (file_exists($promoPath)) {
        $promoLines = @file($promoPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($promoLines !== false) {
            array_shift($promoLines); // header
            foreach ($promoLines as $line) {
                $p = str_getcsv($line);
                $promotions[$p[0]] = [
                    'code' => $p[0],
                    'type' => $p[1],
                    'value' => $p[2],
                    'active' => ($p[3] ?? 'true') !== 'false'
                ];
            }
        }
    }

    // Lecture orders (mélange parsing et validation)
    $orders = [];
    $ordLines = file($ordPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    array_shift($ordLines); // remove header
    foreach ($ordLines as $line) {
        $parts = str_getcsv($line);
        try {
            $qty = intval($parts[3]);
            $price = floatval($parts[4]);

            if ($qty <= 0 || $price < 0) {
                continue; // validation silencieuse
            }

            $orders[] = [
                'id' => $parts[0],
                'customer_id' => $parts[1],
                'product_id' => $parts[2],
                'qty' => $qty,
                'unit_price' => $price,
                'date' => $parts[5] ?? '',
                'promo_code' => $parts[6] ?? '',
                'time' => $parts[7] ?? '12:00'
            ];
        } catch (Exception $e) {
            // Skip silencieux
            continue;
        }
    }

    // Calcul points de fidélité (première duplication)
    $loyaltyPoints = [];
    foreach ($orders as $o) {
        $cid = $o['customer_id'];
        if (!isset($loyaltyPoints[$cid])) {
            $loyaltyPoints[$cid] = 0;
        }
        // Calcul basé sur prix commande
        $loyaltyPoints[$cid] += $o['qty'] * $o['unit_price'] * LOYALTY_RATIO;
    }

    // Groupement par client (logique métier mélangée)
    $totalsByCustomer = [];
    foreach ($orders as $o) {
        $cid = $o['customer_id'];

        // Récupération produit avec fallback
        $prod = $products[$o['product_id']] ?? [];
        $basePrice = $prod['price'] ?? $o['unit_price'];

        // Application promo (logique complexe et bugguée)
        $promoCode = $o['promo_code'];
        $discountRate = 0;
        $fixedDiscount = 0;

        if (!empty($promoCode) && isset($promotions[$promoCode])) {
            $promo = $promotions[$promoCode];
            if ($promo['active']) {
                if ($promo['type'] === 'PERCENTAGE') {
                    $discountRate = floatval($promo['value']) / 100;
                } elseif ($promo['type'] === 'FIXED') {
                    // Bug: appliqué par ligne au lieu de global
                    $fixedDiscount = floatval($promo['value']);
                }
            }
        }

        // Calcul ligne avec réduction promo
        $lineTotal = $o['qty'] * $basePrice * (1 - $discountRate) - $fixedDiscount * $o['qty'];

        // Bonus matin (règle cachée basée sur heure)
        $hour = intval(explode(':', $o['time'])[0]);
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
        $totalsByCustomer[$cid]['weight'] += ($prod['weight'] ?? 1.0) * $o['qty'];
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
        $cust = $customers[$cid] ?? [];
        $name = $cust['name'] ?? 'Unknown';
        $level = $cust['level'] ?? 'BASIC';
        $zone = $cust['shipping_zone'] ?? 'ZONE1';
        $currency = $cust['currency'] ?? 'EUR';

        $sub = $totalsByCustomer[$cid]['subtotal'];

        // Remise par paliers (duplication + magic numbers)
        $disc = 0.0;
        if ($sub > 50) {
            $disc = $sub * 0.05;
        }
        if ($sub > 100) {
            $disc = $sub * 0.10; // écrase la précédente (bug intentionnel)
        }
        if ($sub > 500) {
            $disc = $sub * 0.15;
        }
        if ($sub > 1000 && $level === 'PREMIUM') {
            $disc = $sub * 0.20;
        }

        // Bonus weekend (règle cachée basée sur date)
        $firstOrderDate = $totalsByCustomer[$cid]['items'][0]['date'] ?? '';
        $dayOfWeek = 0;
        if (!empty($firstOrderDate)) {
            $timestamp = strtotime($firstOrderDate);
            if ($timestamp !== false) {
                $dayOfWeek = intval(date('w', $timestamp));
            }
        }
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            $disc = $disc * 1.05; // 5% bonus sur remise
        }

        // Calcul remise fidélité (duplication)
        $loyaltyDiscount = 0.0;
        $pts = $loyaltyPoints[$cid] ?? 0;
        if ($pts > 100) {
            $loyaltyDiscount = min($pts * 0.1, 50.0);
        }
        if ($pts > 500) {
            $loyaltyDiscount = min($pts * 0.15, 100.0); // écrase précédent
        }

        // Plafond remise global (règle cachée)
        $totalDiscount = $disc + $loyaltyDiscount;
        if ($totalDiscount > MAX_DISCOUNT) {
            $totalDiscount = MAX_DISCOUNT;
            // Ajustement proportionnel (logique complexe)
            $ratio = MAX_DISCOUNT / ($disc + $loyaltyDiscount);
            $disc = $disc * $ratio;
            $loyaltyDiscount = $loyaltyDiscount * $ratio;
        }

        // Calcul taxe (gestion spéciale par produit)
        $taxable = $sub - $totalDiscount;
        $tax = 0.0;

        // Vérifier si tous produits taxables
        $allTaxable = true;
        foreach ($totalsByCustomer[$cid]['items'] as $item) {
            $prod = $products[$item['product_id']] ?? null;
            if ($prod && isset($prod['taxable']) && $prod['taxable'] === false) {
                $allTaxable = false;
                break;
            }
        }

        if ($allTaxable) {
            $tax = round($taxable * TAX, 2); // Arrondi 2 décimales
        } else {
            // Calcul taxe par ligne (plus complexe)
            foreach ($totalsByCustomer[$cid]['items'] as $item) {
                $prod = $products[$item['product_id']] ?? null;
                if ($prod && ($prod['taxable'] ?? true) !== false) {
                    $itemTotal = $item['qty'] * ($prod['price'] ?? $item['unit_price']);
                    $tax += $itemTotal * TAX;
                }
            }
            $tax = round($tax, 2);
        }

        // Frais de port complexes (duplication)
        $ship = 0.0;
        $weight = $totalsByCustomer[$cid]['weight'];

        if ($sub < SHIPPING_LIMIT) {
            $shipZone = $shippingZones[$zone] ?? ['base' => 5.0, 'per_kg' => 0.5];
            $baseShip = $shipZone['base'];

            if ($weight > 10) {
                $ship = $baseShip + ($weight - 10) * $shipZone['per_kg'];
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
            $handling = HANDLING_FEE;
        }
        if ($itemCount > 20) {
            $handling = HANDLING_FEE * 2; // double pour grosses commandes
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
