<?php

namespace Tests\Calculators;

require_once __DIR__ . '/../../src/OrderReport.php';
require_once __DIR__ . '/../../src/Calculators/ShippingCalculator.php';
require_once __DIR__ . '/../../src/Models/ShippingZone.php';

use OrderReport\Calculators\ShippingCalculator;
use OrderReport\Models\ShippingZone;
use PHPUnit\Framework\TestCase;

class ShippingCalculatorTest extends TestCase
{
    public function testCalculateShippingBelowLimit(): void
    {
        $shippingZones = [
            'ZONE1' => new ShippingZone('ZONE1', 5.0, 0.5)
        ];
        
        $this->assertEquals(5.0, ShippingCalculator::calculateShipping(30.0, 3.0, 'ZONE1', $shippingZones));
        $this->assertEquals(5.9, ShippingCalculator::calculateShipping(30.0, 8.0, 'ZONE1', $shippingZones));
        $this->assertEquals(7.5, ShippingCalculator::calculateShipping(30.0, 15.0, 'ZONE1', $shippingZones));
    }

    public function testCalculateShippingAboveLimit(): void
    {
        $shippingZones = [
            'ZONE1' => new ShippingZone('ZONE1', 5.0, 0.5)
        ];
        
        $this->assertEquals(0.0, ShippingCalculator::calculateShipping(100.0, 10.0, 'ZONE1', $shippingZones));
        $this->assertEquals(2.5, ShippingCalculator::calculateShipping(100.0, 30.0, 'ZONE1', $shippingZones));
    }

    public function testCalculateShippingZone3And4(): void
    {
        $shippingZones = [
            'ZONE3' => new ShippingZone('ZONE3', 5.0, 0.5)
        ];
        
        $shipping = ShippingCalculator::calculateShipping(30.0, 15.0, 'ZONE3', $shippingZones);
        $this->assertEquals(9.0, $shipping);
    }

    public function testCalculateHandling(): void
    {
        $this->assertEquals(0.0, ShippingCalculator::calculateHandling(5));
        $this->assertEquals(2.5, ShippingCalculator::calculateHandling(15));
        $this->assertEquals(5.0, ShippingCalculator::calculateHandling(25));
    }
}

