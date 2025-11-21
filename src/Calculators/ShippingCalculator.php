<?php

namespace OrderReport\Calculators;

use OrderReport\OrderConfig;
use OrderReport\Models\ShippingZone;

class ShippingCalculator
{
    public static function calculateShipping(
        float $subtotal,
        float $weight,
        string $zone,
        array $shippingZones
    ): float {
        if ($subtotal < OrderConfig::SHIPPING_LIMIT) {
            return self::calculateShippingWithLimit($weight, $zone, $shippingZones);
        }
        
        return self::calculateHandlingFeeForHeavyWeight($weight);
    }

    private static function calculateShippingWithLimit(
        float $weight,
        string $zone,
        array $shippingZones
    ): float {
        $shipZone = $shippingZones[$zone] ?? new ShippingZone(zone: $zone, base: 5.0, perKg: 0.5);
        $baseShip = $shipZone->base;

        if ($weight > 10) {
            $ship = $baseShip + ($weight - 10) * $shipZone->perKg;
        } elseif ($weight > 5) {
            $ship = $baseShip + ($weight - 5) * 0.3;
        } else {
            $ship = $baseShip;
        }

        if ($zone === 'ZONE3' || $zone === 'ZONE4') {
            $ship = $ship * 1.2;
        }

        return $ship;
    }

    private static function calculateHandlingFeeForHeavyWeight(float $weight): float
    {
        if ($weight > 20) {
            return ($weight - 20) * 0.25;
        }
        return 0.0;
    }

    public static function calculateHandling(int $itemCount): float
    {
        if ($itemCount > 20) {
            return OrderConfig::HANDLING_FEE * 2;
        }
        if ($itemCount > 10) {
            return OrderConfig::HANDLING_FEE;
        }
        return 0.0;
    }
}

