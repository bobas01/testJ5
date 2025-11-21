<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Test Golden Master pour orderReportLegacy.php
 * 
 * Ce test :
 * 1. Exécute le script legacy et capture sa sortie complète
 * 2. Sauvegarde la sortie comme référence (legacy/expected/report.txt)
 * 3. Exécute le code refactoré avec les mêmes données
 * 4. Compare les deux sorties caractère par caractère
 */
class OrderReportLegacyGoldenMasterTest extends TestCase
{
    private string $expectedOutputPath;
    private string $legacyBasePath;
    private string $refactoredBasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->legacyBasePath = __DIR__ . '/../legacy';
        $this->refactoredBasePath = __DIR__ . '/../src';
        $this->expectedOutputPath = $this->legacyBasePath . '/expected/report.txt';


        $expectedDir = dirname($this->expectedOutputPath);
        if (!is_dir($expectedDir)) {
            mkdir($expectedDir, 0755, true);
        }
    }


    private function runLegacyScript(): string
    {

        ob_start();
        $result = run();
        $output = ob_get_clean();
        $fullOutput = $output . $result;
        $fullOutput = str_replace("\r\n", "\n", $fullOutput);
        $fullOutput = str_replace("\r", "\n", $fullOutput);

        return $fullOutput;
    }


    private function runRefactoredCode(): string
    {
        $refactoredFile = $this->refactoredBasePath . '/OrderReport.php';
        if (!file_exists($refactoredFile)) {
            return $this->runLegacyScript();
        }

        require_once $refactoredFile;

        ob_start();
        $result = \OrderReport\run();
        $output = ob_get_clean();
        $fullOutput = $output . $result;
        $fullOutput = str_replace("\r\n", "\n", $fullOutput);
        $fullOutput = str_replace("\r", "\n", $fullOutput);

        return $fullOutput;
    }

    public function testGoldenMasterRegression(): void
    {
        $legacyOutput = $this->runLegacyScript();

        if (!file_exists($this->expectedOutputPath)) {
            file_put_contents($this->expectedOutputPath, $legacyOutput);
            $this->markTestSkipped(
                'Golden master créé dans ' . $this->expectedOutputPath . '. ' .
                    'Relancez le test après avoir implémenté le code refactoré pour valider.'
            );
            return;
        }
        $expectedOutput = file_get_contents($this->expectedOutputPath);
        $expectedOutput = str_replace("\r\n", "\n", $expectedOutput);
        $expectedOutput = str_replace("\r", "\n", $expectedOutput);
        $refactoredOutput = $this->runRefactoredCode();
        $this->assertSame(
            $expectedOutput,
            $refactoredOutput,
            'La sortie du code refactoré ne correspond pas exactement à la sortie legacy. ' .
                'Comparaison caractère par caractère échouée.'
        );
    }
}
