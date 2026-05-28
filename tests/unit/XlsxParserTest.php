<?php
/**
 * DataDachs – Tests für XlsxParser
 */

namespace DataDachs\Tests;

use DataDachs\Parser\XlsxParser;
use DataDachs\Service\PiiDetector;
use DataDachs\Service\FakerEngine;
use PHPUnit\Framework\TestCase;

class XlsxParserTest extends TestCase
{
    private string $testXlsxPath;
    private XlsxParser $parser;

    protected function setUp(): void
    {
        $this->testXlsxPath = __DIR__ . '/../../storage/test.xlsx';
        
        // Einfache XLSX-Datei erstellen
        $this->createTestXlsx();
        
        $detector = new PiiDetector();
        $faker = new FakerEngine();
        $this->parser = new XlsxParser($detector, $faker);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testXlsxPath)) {
            unlink($this->testXlsxPath);
        }
    }

    private function createTestXlsx(): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Header
        $sheet->setCellValue('A1', 'firstname');
        $sheet->setCellValue('B1', 'lastname');
        $sheet->setCellValue('C1', 'email');
        
        // Daten
        $sheet->setCellValue('A2', 'Max');
        $sheet->setCellValue('B2', 'Müller');
        $sheet->setCellValue('C2', 'max@example.com');
        
        $sheet->setCellValue('A3', 'Anna');
        $sheet->setCellValue('B3', 'Schmidt');
        $sheet->setCellValue('C3', 'anna@example.com');
        
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($this->testXlsxPath);
    }

    public function testAnalyze(): void
    {
        if (!XlsxParser::isAvailable()) {
            $this->markTestSkipped('ZIP-Extension nicht verfügbar');
        }
        
        $analysis = $this->parser->analyze($this->testXlsxPath);
        
        $this->assertEquals('xlsx', $analysis['file_type']);
        $this->assertEquals(1, $analysis['total_sheets']);
    }

    public function testPseudonymize(): void
    {
        if (!XlsxParser::isAvailable()) {
            $this->markTestSkipped('ZIP-Extension nicht verfügbar');
        }
        
        $rules = [
            'Sheet1' => [
                'firstname' => ['action' => 'pseudonymize', 'type' => 'first_name'],
                'lastname' => ['action' => 'pseudonymize', 'type' => 'last_name'],
                'email' => ['action' => 'pseudonymize', 'type' => 'email'],
            ],
        ];
        
        $resultPath = $this->parser->pseudonymize($this->testXlsxPath, $rules);
        
        $this->assertFileExists($resultPath);
        
        // Prüfen ob Werte ersetzt wurden
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($resultPath);
        $sheet = $spreadsheet->getActiveSheet();
        
        $this->assertNotEquals('Max', $sheet->getCell('A2')->getValue());
        $this->assertNotEquals('Müller', $sheet->getCell('B2')->getValue());
        $this->assertNotEquals('max@example.com', $sheet->getCell('C2')->getValue());
        
        unlink($resultPath);
    }

    public function testToCsv(): void
    {
        if (!XlsxParser::isAvailable()) {
            $this->markTestSkipped('ZIP-Extension nicht verfügbar');
        }
        
        $csv = $this->parser->toCsv($this->testXlsxPath);
        
        $this->assertStringContainsString('firstname', $csv);
        $this->assertStringContainsString('Max', $csv);
    }
}
