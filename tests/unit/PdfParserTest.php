<?php
/**
 * DataDachs – Tests für PdfParser
 */

namespace DataDachs\Tests;

use DataDachs\Parser\PdfParser;
use DataDachs\Service\PiiDetector;
use DataDachs\Service\FakerEngine;
use PHPUnit\Framework\TestCase;

class PdfParserTest extends TestCase
{
    private string $testPdfPath;
    private PdfParser $parser;

    protected function setUp(): void
    {
        $this->testPdfPath = __DIR__ . '/../../storage/test.pdf';
        
        // Einfache PDF-Datei erstellen (Text-basiert)
        $this->createTestPdf();
        
        $detector = new PiiDetector();
        $faker = new FakerEngine();
        $this->parser = new PdfParser($detector, $faker);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testPdfPath)) {
            unlink($this->testPdfPath);
        }
    }

    private function createTestPdf(): void
    {
        // Einfache text-basierte PDF
        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n";
        $pdf .= "4 0 obj\n<< /Length 100 >>\nstream\n";
        $pdf .= "BT /F1 12 Tf 100 700 Td (Max Mueller) Tj\n";
        $pdf .= "0 -20 Td (max@example.com) Tj\n";
        $pdf .= "0 -20 Td (Tel: 0123 456789) Tj\n";
        $pdf .= "ET\nendstream\nendobj\n";
        $pdf .= "xref\n0 5\n0000000000 65535 f\n";
        $pdf .= "trailer\n<< /Size 5 /Root 1 0 R >>\n";
        $pdf .= "startxref\n0\n%%EOF";
        
        file_put_contents($this->testPdfPath, $pdf);
    }

    public function testAnalyze(): void
    {
        $analysis = $this->parser->analyze($this->testPdfPath);
        
        $this->assertEquals('pdf', $analysis['file_type']);
        $this->assertArrayHasKey('findings', $analysis);
        $this->assertArrayHasKey('text_length', $analysis);
    }

    public function testPseudonymize(): void
    {
        $rules = [
            ['type' => 'email', 'action' => 'pseudonymize'],
        ];
        
        $resultPath = $this->parser->pseudonymize($this->testPdfPath, $rules);
        
        $this->assertFileExists($resultPath);
        
        $content = file_get_contents($resultPath);
        $this->assertStringNotContainsString('max@example.com', $content);
        
        unlink($resultPath);
    }

    public function testIsPdftotextAvailable(): void
    {
        // Sollte false sein auf dem Test-System (kein poppler-utils)
        $this->assertFalse(PdfParser::isPdftotextAvailable());
    }
}
