<?php

namespace Tests;

require_once __DIR__ . '/../src/CsvReader.php';

use OrderReport\CsvReader;
use PHPUnit\Framework\TestCase;

class CsvReaderTest extends TestCase
{
    private string $testDataPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDataPath = __DIR__ . '/../legacy/data';
    }

    public function testReadCustomers(): void
    {
        $customers = CsvReader::readCustomers($this->testDataPath . '/customers.csv');
        
        $this->assertIsArray($customers);
        $this->assertGreaterThan(0, count($customers));
        $this->assertArrayHasKey('C001', $customers);
        
        $customer = $customers['C001'];
        $this->assertEquals('C001', $customer->id);
        $this->assertNotEmpty($customer->name);
    }

    public function testReadProducts(): void
    {
        $products = CsvReader::readProducts($this->testDataPath . '/products.csv');
        
        $this->assertIsArray($products);
        $this->assertGreaterThan(0, count($products));
        
        $firstProduct = reset($products);
        $this->assertIsObject($firstProduct);
        $this->assertObjectHasProperty('id', $firstProduct);
        $this->assertObjectHasProperty('price', $firstProduct);
    }

    public function testReadShippingZones(): void
    {
        $zones = CsvReader::readShippingZones($this->testDataPath . '/shipping_zones.csv');
        
        $this->assertIsArray($zones);
        $this->assertGreaterThan(0, count($zones));
        
        $firstZone = reset($zones);
        $this->assertIsObject($firstZone);
        $this->assertObjectHasProperty('zone', $firstZone);
        $this->assertObjectHasProperty('base', $firstZone);
    }

    public function testReadPromotions(): void
    {
        $promotions = CsvReader::readPromotions($this->testDataPath . '/promotions.csv');
        
        $this->assertIsArray($promotions);
        
        if (count($promotions) > 0) {
            $firstPromo = reset($promotions);
            $this->assertIsObject($firstPromo);
            $this->assertObjectHasProperty('code', $firstPromo);
            $this->assertObjectHasProperty('type', $firstPromo);
        }
    }

    public function testReadOrders(): void
    {
        $orders = CsvReader::readOrders($this->testDataPath . '/orders.csv');
        
        $this->assertIsArray($orders);
        $this->assertGreaterThan(0, count($orders));
        
        $firstOrder = $orders[0];
        $this->assertIsObject($firstOrder);
        $this->assertObjectHasProperty('id', $firstOrder);
        $this->assertObjectHasProperty('customerId', $firstOrder);
        $this->assertObjectHasProperty('qty', $firstOrder);
        $this->assertGreaterThan(0, $firstOrder->qty);
    }
}

