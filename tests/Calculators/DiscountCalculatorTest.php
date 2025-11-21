<?php

namespace Tests\Calculators;

require_once __DIR__ . '/../../src/OrderReport.php';
require_once __DIR__ . '/../../src/Calculators/DiscountCalculator.php';

use OrderReport\Calculators\DiscountCalculator;
use PHPUnit\Framework\TestCase;

class DiscountCalculatorTest extends TestCase
{
    public function testCalculateVolumeDiscountBasic(): void
    {
        $this->assertEquals(0.0, DiscountCalculator::calculateVolumeDiscount(30.0, 'BASIC'));
        $this->assertEquals(0.0, DiscountCalculator::calculateVolumeDiscount(50.0, 'BASIC'));
        $this->assertEquals(5.0, DiscountCalculator::calculateVolumeDiscount(100.0, 'BASIC'));
        $this->assertEquals(50.0, DiscountCalculator::calculateVolumeDiscount(500.0, 'BASIC'));
    }

    public function testCalculateVolumeDiscountPremium(): void
    {
        $this->assertEquals(0.0, DiscountCalculator::calculateVolumeDiscount(30.0, 'PREMIUM'));
        $this->assertEquals(150.0, DiscountCalculator::calculateVolumeDiscount(1000.0, 'PREMIUM'));
        $this->assertEquals(300.0, DiscountCalculator::calculateVolumeDiscount(1500.0, 'PREMIUM'));
    }

    public function testApplyWeekendBonus(): void
    {
        $discount = 100.0;
        
        $this->assertEquals(100.0, DiscountCalculator::applyWeekendBonus($discount, ''));
        $this->assertEquals(100.0, DiscountCalculator::applyWeekendBonus($discount, '2024-01-15'));
        
        $saturday = '2024-01-13';
        $sunday = '2024-01-14';
        
        $this->assertEquals(105.0, DiscountCalculator::applyWeekendBonus($discount, $saturday));
        $this->assertEquals(105.0, DiscountCalculator::applyWeekendBonus($discount, $sunday));
    }

    public function testCalculateLoyaltyDiscount(): void
    {
        $this->assertEquals(0.0, DiscountCalculator::calculateLoyaltyDiscount(50.0));
        $this->assertEquals(0.0, DiscountCalculator::calculateLoyaltyDiscount(100.0));
        $this->assertEquals(50.0, DiscountCalculator::calculateLoyaltyDiscount(500.0));
        $this->assertEquals(90.0, DiscountCalculator::calculateLoyaltyDiscount(600.0));
        $this->assertEquals(100.0, DiscountCalculator::calculateLoyaltyDiscount(1000.0));
    }

    public function testApplyMaxDiscountCap(): void
    {
        $result = DiscountCalculator::applyMaxDiscountCap(100.0, 50.0);
        $this->assertEquals(100.0, $result['volume']);
        $this->assertEquals(50.0, $result['loyalty']);
        $this->assertEquals(150.0, $result['total']);
        
        $result = DiscountCalculator::applyMaxDiscountCap(150.0, 100.0);
        $this->assertEquals(120.0, $result['volume']);
        $this->assertEquals(80.0, $result['loyalty']);
        $this->assertEquals(200.0, $result['total']);
    }
}

