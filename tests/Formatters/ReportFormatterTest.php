<?php

namespace Tests\Formatters;

require_once __DIR__ . '/../../src/Formatters/ReportFormatter.php';

use OrderReport\Formatters\ReportFormatter;
use PHPUnit\Framework\TestCase;

class ReportFormatterTest extends TestCase
{
    public function testFormatCustomerReportStructure(): void
    {
        $lines = ReportFormatter::formatCustomerReport(
            'Alice Martin',
            'C001',
            'BASIC',
            'ZONE1',
            'EUR',
            100.0,
            10.0,
            5.0,
            5.0,
            0.0,
            18.0,
            1.0,
            'ZONE1',
            5.0,
            0.0,
            0.0,
            2,
            108.0,
            10
        );

        $this->assertIsArray($lines);
        $this->assertGreaterThan(10, count($lines));
        $this->assertStringContainsString('Customer: Alice Martin (C001)', $lines[0]);
        $this->assertStringContainsString('Level: BASIC', $lines[1]);
        $this->assertStringContainsString('Subtotal:', $lines[2]);
        $this->assertStringContainsString('Discount:', $lines[3]);
        $this->assertStringContainsString('Tax:', implode("\n", $lines));
        $this->assertStringContainsString('Total:', implode("\n", $lines));
    }

    public function testFormatAmounts(): void
    {
        $lines = ReportFormatter::formatCustomerReport(
            'Test',
            'C001',
            'BASIC',
            'ZONE1',
            'EUR',
            100.0,
            10.0,
            5.0,
            5.0,
            0.0,
            18.0,
            1.0,
            'ZONE1',
            5.0,
            0.0,
            0.0,
            2,
            108.0,
            10
        );

        $reportText = implode("\n", $lines);

        $this->assertMatchesRegularExpression('/Subtotal: \d+\.\d{2}/', $reportText);
        $this->assertMatchesRegularExpression('/Discount: \d+\.\d{2}/', $reportText);
        $this->assertMatchesRegularExpression('/Tax: \d+\.\d{2}/', $reportText);
        $this->assertMatchesRegularExpression('/Total: \d+\.\d{2} EUR/', $reportText);

        $this->assertStringContainsString('Subtotal: 100.00', $reportText);
        $this->assertStringContainsString('Discount: 10.00', $reportText);
        $this->assertStringContainsString('Tax: 18.00', $reportText);
        $this->assertStringContainsString('Total: 108.00 EUR', $reportText);
    }

    public function testFormatCustomerReportWithMorningBonus(): void
    {
        $lines = ReportFormatter::formatCustomerReport(
            'Bob',
            'C002',
            'PREMIUM',
            'ZONE2',
            'USD',
            200.0,
            20.0,
            10.0,
            10.0,
            5.0,
            36.0,
            1.1,
            'ZONE2',
            10.0,
            5.0,
            2.5,
            5,
            250.0,
            20
        );

        $this->assertStringContainsString('Morning bonus:', implode("\n", $lines));
    }

    public function testFormatSummaryStructure(): void
    {
        $summary = ReportFormatter::formatSummary(1000.0, 200.0);

        $this->assertIsArray($summary);
        $this->assertCount(2, $summary);
        $this->assertStringContainsString('Grand Total:', $summary[0]);
        $this->assertStringContainsString('Total Tax Collected:', $summary[1]);
    }

    public function testFormatReport(): void
    {
        $customerReports = [
            ['Line 1', 'Line 2'],
            ['Line 3', 'Line 4']
        ];
        $summary = ['Summary 1', 'Summary 2'];

        $result = ReportFormatter::formatReport($customerReports, $summary);

        $this->assertStringContainsString('Line 1', $result);
        $this->assertStringContainsString('Line 4', $result);
        $this->assertStringContainsString('Summary 2', $result);
    }
}
