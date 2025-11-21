<?php

namespace OrderReport;

require_once __DIR__ . '/Models/Customer.php';
require_once __DIR__ . '/Models/Product.php';
require_once __DIR__ . '/Models/Order.php';
require_once __DIR__ . '/Models/ShippingZone.php';
require_once __DIR__ . '/Models/Promotion.php';

use OrderReport\Models\Customer;
use OrderReport\Models\Product;
use OrderReport\Models\Order;
use OrderReport\Models\ShippingZone;
use OrderReport\Models\Promotion;

class CsvReader
{
    public static function readCustomers(string $csvPath): array
    {
        $customers = [];
        $custFile = fopen($csvPath, 'r');
        if ($custFile === false) {
            return $customers;
        }

        $header = fgetcsv($custFile); // skip header
        while (($row = fgetcsv($custFile)) !== false) {
            $customers[$row[0]] = new Customer(
                id: $row[0],
                name: $row[1],
                level: $row[2] ?? 'BASIC',
                shippingZone: $row[3] ?? 'ZONE1',
                currency: $row[4] ?? 'EUR'
            );
        }
        fclose($custFile);

        return $customers;
    }

    public static function readProducts(string $csvPath): array
    {
        $products = [];
        if (($handle = fopen($csvPath, 'r')) === false) {
            return $products;
        }

        $headers = fgetcsv($handle);
        while (($data = fgetcsv($handle)) !== false) {
            try {
                $products[$data[0]] = new Product(
                    id: $data[0],
                    name: $data[1],
                    category: $data[2],
                    price: floatval($data[3]),
                    weight: floatval($data[4] ?? 1.0),
                    taxable: ($data[5] ?? 'true') === 'true'
                );
            } catch (\Exception $e) {
                continue;
            }
        }
        fclose($handle);

        return $products;
    }

    public static function readShippingZones(string $csvPath): array
    {
        $shippingZones = [];
        $shipContent = file_get_contents($csvPath);
        if ($shipContent === false) {
            return $shippingZones;
        }

        $shipLines = explode("\n", $shipContent);
        array_shift($shipLines); // remove header
        foreach ($shipLines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $p = str_getcsv($line);
            $shippingZones[$p[0]] = new ShippingZone(
                zone: $p[0],
                base: floatval($p[1]),
                perKg: floatval($p[2] ?? 0.5)
            );
        }

        return $shippingZones;
    }

    public static function readPromotions(string $csvPath): array
    {
        $promotions = [];
        if (!file_exists($csvPath)) {
            return $promotions;
        }

        $promoLines = @file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($promoLines === false) {
            return $promotions;
        }

        array_shift($promoLines); // header
        foreach ($promoLines as $line) {
            $p = str_getcsv($line);
            $promotions[$p[0]] = new Promotion(
                code: $p[0],
                type: $p[1],
                value: $p[2],
                active: ($p[3] ?? 'true') !== 'false'
            );
        }

        return $promotions;
    }

    public static function readOrders(string $csvPath): array
    {
        $orders = [];
        $ordLines = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($ordLines === false) {
            return $orders;
        }

        array_shift($ordLines); // remove header
        foreach ($ordLines as $line) {
            $parts = str_getcsv($line);
            try {
                $qty = intval($parts[3]);
                $price = floatval($parts[4]);

                if ($qty <= 0 || $price < 0) {
                    continue;
                }

                $orders[] = new Order(
                    id: $parts[0],
                    customerId: $parts[1],
                    productId: $parts[2],
                    qty: $qty,
                    unitPrice: $price,
                    date: $parts[5] ?? '',
                    promoCode: $parts[6] ?? '',
                    time: $parts[7] ?? '12:00'
                );
            } catch (\Exception $e) {
                continue;
            }
        }

        return $orders;
    }
}
