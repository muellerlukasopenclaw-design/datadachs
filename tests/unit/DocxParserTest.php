<?php
/**
 * DataDachs – Tests für DocxParser
 */

namespace DataDachs\Tests;

use DataDachs\Parser\DocxParser;
use DataDachs\Service\PiiDetector;
use DataDachs\Service\FakerEngine;
use PHPUnit\Framework\TestCase;

class DocxParserTest extends TestCase
{
    private string $testDocxPath;
    private DocxParser $parser;

    protected function setUp(): void
    {
        $this->testDocxPath = __DIR__ . '/../../storage/test.docx';
        
        // Einfache DOCX-Datei erstellen
        $this->createTestDocx();
        
        $detector = new PiiDetector();
        $faker = new FakerEngine();
        $this->parser = new DocxParser($detector, $faker);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDocxPath)) {
            unlink($this->testDocxPath);
        }
    }

    private function createTestDocx(): void
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Max Müller');
        $section->addText('max@example.com');
        $section->addText('Tel: 0123 456789');
        
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($this->testDocxPath);
    }

    public function testAnalyze(): void
    {
        if (!DocxParser::isAvailable()) {
            $this->markTestSkipped('ZIP-Extension nicht verfügbar');
        }
        
        $analysis = $this->parser->analyze($this->testDocxPath);
        
        $this->assertEquals('docx', $analysis['file_type']);
        $this->assertGreaterThan(0, $analysis['total_findings']);
        
        // E-Mail sollte erkannt werden
        $hasEmail = false;
        foreach ($analysis['findings'] as $finding) {
            if ($finding['type'] === 'email') {
                $hasEmail = true;
                break;
            }
        }
        $this->assertTrue($hasEmail, 'E-Mail sollte erkannt werden');
    }

    public function testPseudonymize(): void
    {
        if (!DocxParser::isAvailable()) {
            $this->markTestSkipped('ZIP-Extension nicht verfügbar');
        }
        
        $rules = [
            ['type' => 'email', 'action' => 'pseudonymize'],
        ];
        
        $resultPath = $this->parser->pseudonymize($this->testDocxPath, $rules);
        
        $this->assertFileExists($resultPath);
        
        // Prüfen ob E-Mail ersetzt wurde
        $resultDocx = \PhpOffice\PhpWord\IOFactory::load($resultPath);
        $text = $this->extractText($resultDocx);
        
        $this->assertStringNotContainsString('max@example.com', $text);
        
        unlink($resultPath);
    }

    private function extractText(\PhpOffice\PhpWord\PhpWord $phpWord): string
    {
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $t = $element->getText();
                    if (is_string($t)) {
                        $text .= $t;
                    }
                }
            }
        }
        return $text;
    }
}
