<?php
/**
 * DataDachs – DOCX Parser
 * Parst Word-Dokumente und pseudonymisiert PII im Text
 */

namespace DataDachs\Parser;

use DataDachs\Service\PiiDetector;
use DataDachs\Service\FakerEngine;
use DataDachs\Service\PreserveRuleService;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class DocxParser
{
    private PiiDetector $detector;
    private FakerEngine $faker;
    private ?PreserveRuleService $preserveService;
    private array $textCache = [];
    
    public function __construct(PiiDetector $detector, FakerEngine $faker, ?PreserveRuleService $preserveService = null)
    {
        $this->detector = $detector;
        $this->faker = $faker;
        $this->preserveService = $preserveService;
    }
    
    /**
     * Prüft ob DOCX-Verarbeitung verfügbar ist
     */
    public static function isAvailable(): bool
    {
        return class_exists('ZipArchive') || class_exists('PhpOffice\PhpWord\PhpWord');
    }
    
    /**
     * Analysiert ein DOCX auf PII-Vorkommen
     */
    public function analyze(string $filePath): array
    {
        if (!self::isAvailable()) {
            return [
                'file_type' => 'docx',
                'error' => 'ZIP-Extension nicht verfügbar. DOCX-Verarbeitung deaktiviert.',
                'total_findings' => 0,
                'findings' => [],
            ];
        }
        
        $phpWord = IOFactory::load($filePath);
        $text = $this->extractText($phpWord);
        
        $findings = [];
        
        // Regex-basierte Erkennung
        foreach ($this->detector->getAvailableTypes() as $type => $faker) {
            $patterns = $this->getPatternsForType($type);
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $findings[] = [
                            'type' => $type,
                            'value' => $match[0],
                            'position' => $match[1],
                            'method' => 'regex',
                            'action' => 'pseudonymize',
                        ];
                    }
                }
            }
        }
        
        return [
            'file_type' => 'docx',
            'total_findings' => count($findings),
            'findings' => $findings,
        ];
    }
    
    /**
     * Pseudonymisiert ein DOCX-Dokument
     */
    public function pseudonymize(string $filePath, array $confirmedRules): string
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('ZIP-Extension nicht verfügbar. DOCX-Verarbeitung deaktiviert.');
        }
        
        $phpWord = IOFactory::load($filePath);
        
        // Alle Sections durchgehen
        foreach ($phpWord->getSections() as $section) {
            $this->processElements($section->getElements(), $confirmedRules);
        }
        
        // Temporäre Datei speichern
        $tempPath = tempnam(sys_get_temp_dir(), 'datadachs_docx_');
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);
        
        return $tempPath;
    }
    
    /**
     * Extrahiert reinen Text für Analyse
     */
    private function extractText(PhpWord $phpWord): string
    {
        $text = '';
        foreach ($phpWord->getSections() as $section) {
            $text .= $this->extractTextFromElements($section->getElements());
        }
        return $text;
    }
    
    /**
     * Rekursive Text-Extraktion
     */
    private function extractTextFromElements(array $elements): string
    {
        $text = '';
        
        foreach ($elements as $element) {
            if (method_exists($element, 'getText')) {
                $t = $element->getText();
                if (is_string($t)) {
                    $text .= $t . ' ';
                }
            }
            
            // Nested elements (Tabellen, etc.)
            if (method_exists($element, 'getRows')) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $text .= $this->extractTextFromElements($cell->getElements());
                    }
                }
            }
            
            if (method_exists($element, 'getElements')) {
                $text .= $this->extractTextFromElements($element->getElements());
            }
        }
        
        return $text;
    }
    
    /**
     * Verarbeitet Elemente und ersetzt PII
     */
    private function processElements(array $elements, array $rules): void
    {
        foreach ($elements as $element) {
            // Text-Element
            if (method_exists($element, 'getText') && method_exists($element, 'setText')) {
                $text = $element->getText();
                if (is_string($text)) {
                    $newText = $this->replacePiiInText($text, $rules);
                    if ($newText !== $text) {
                        $element->setText($newText);
                    }
                }
            }
            
            // Tabelle
            if (method_exists($element, 'getRows')) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $this->processElements($cell->getElements(), $rules);
                    }
                }
            }
            
            // Container
            if (method_exists($element, 'getElements')) {
                $this->processElements($element->getElements(), $rules);
            }
        }
    }
    
    /**
     * Ersetzt PII in einem Text-String
     */
    private function replacePiiInText(string $text, array $rules): string
    {
        foreach ($rules as $rule) {
            if (($rule['action'] ?? 'keep') !== 'pseudonymize') {
                continue;
            }
            
            $type = $rule['type'];
            $patterns = $this->getPatternsForType($type);
            
            foreach ($patterns as $pattern) {
                $text = preg_replace_callback($pattern, function ($match) use ($type) {
                    $original = $match[0];
                    
                    // Preserve Rules prüfen
                    if ($this->preserveService && $this->preserveService->shouldPreserve($original)) {
                        return $original;
                    }
                    
                    return $this->faker->fake($type, $original);
                }, $text);
            }
        }
        
        return $text;
    }
    
    /**
     * Liefert Regex-Muster für einen PII-Typ
     */
    private function getPatternsForType(string $type): array
    {
        return match ($type) {
            'email' => ['/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/'],
            'phone' => ['/\b(?:\+49|0)[\s\-/]?[\d\s\-/]{6,20}\b/'],
            'iban' => ['/\b[A-Z]{2}\d{2}[\s]?[A-Z0-9]{4}[\s]?[A-Z0-9]{4}[\s]?[A-Z0-9]{4}[\s]?[A-Z0-9]{4}[\s]?[A-Z0-9]{4}[\s]?[A-Z0-9]{2}\b/'],
            'ipv4' => ['/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/'],
            'url' => ['/\bhttps?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/=]*)\b/'],
            'uuid' => ['/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/'],
            default => [],
        };
    }
}
