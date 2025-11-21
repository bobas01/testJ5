<?php

namespace Tests\Calculators;

require_once __DIR__ . '/../../src/OrderReport.php';
require_once __DIR__ . '/../../src/Calculators/TaxCalculator.php';
require_once __DIR__ . '/../../src/Models/Order.php';
require_once __DIR__ . '/../../src/Models/Product.php';

use OrderReport\Calculators\TaxCalculator;
use OrderReport\Models\Order;
use OrderReport\Models\Product;
use PHPUnit\Framework\TestCase;

class TaxCalculatorTest extends TestCase
{
    public function testCalculateTaxAllTaxable(): void
    {
        $items = [
            new Order('O1', 'C1', 'P1', 2, 10.0, '2024-01-01', '', '12:00'),
            new Order('O2', 'C1', 'P2', 1, 20.0, '2024-01-01', '', '12:00')
        ];
        
        $products = [
            'P1' => new Product('P1', 'Product 1', 'Cat1', 10.0, 1.0, true),
            'P2' => new Product('P2', 'Product 2', 'Cat2', 20.0, 1.0, true)
        ];
        
        $taxableAmount = 40.0;
        $tax = TaxCalculator::calculateTax($taxableAmount, $items, $products);
        
        $this->assertEquals(8.0, $tax);
    }

    public function testCalculateTaxWithNonTaxableProducts(): void
    {
        $items = [
            new Order('O1', 'C1', 'P1', 2, 10.0, '2024-01-01', '', '12:00'),
            new Order('O2', 'C1', 'P2', 1, 20.0, '2024-01-01', '', '12:00')
        ];
        
        $products = [
            'P1' => new Product('P1', 'Product 1', 'Cat1', 10.0, 1.0, true),
            'P2' => new Product('P2', 'Product 2', 'Cat2', 20.0, 1.0, false)
        ];
        
        $taxableAmount = 40.0;
        $tax = TaxCalculator::calculateTax($taxableAmount, $items, $products);
        
        $this->assertEquals(4.0, $tax);
    }

    public function testCalculateTaxWithMissingProduct(): void
    {
        $items = [
            new Order('O1', 'C1', 'P1', 2, 10.0, '2024-01-01', '', '12:00')
        ];
        
        $products = [];
        
        $taxableAmount = 20.0;
        $tax = TaxCalculator::calculateTax($taxableAmount, $items, $products);
        
        $this->assertEquals(4.0, $tax);
    }
}

