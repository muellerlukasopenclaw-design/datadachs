<?php
/**
 * DataDachs – PDF Parser
 * Parst PDF-Dokumente und pseudonymisiert PII im Text
 * 
 * Hinweis: Für PDF wird pdftotext (poppler-utils) oder ein PHP-PDF-Parser benötigt.
 * Ohne externe Tools: Regex-basierte Ersetzung im Roh-Text.
 */

namespace DataDachs\Parser;

use DataDachs\Service\PiiDetector;
use DataDachs\Service\FakerEngine;
use DataDachs\Service\PreserveRuleService;

class PdfParser
{
    private PiiDetector $detector;
    private FakerEngine $faker;
    private ?PreserveRuleService $preserveService;
    
    public function __construct(PiiDetector $detector, FakerEngine $faker, ?PreserveRuleService $preserveService = null)
    {
        $this->detector = $detector;
        $this->faker = $faker;
        $this->preserveService = $preserveService;
    }
    
    /**
     * Analysiert ein PDF auf PII-Vorkommen
     * Versucht zuerst pdftotext, dann Fallback auf PHP-Parser
     */
    public function analyze(string $filePath): array
    {
        $text = $this->extractText($filePath);
        
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
            'file_type' => 'pdf',
            'total_findings' => count($findings),
            'text_length' => strlen($text),
            'findings' => $findings,
        ];
    }
    
    /**
     * Pseudonymisiert ein PDF-Dokument
     * 
     * HINWEIS: PDF-Pseudonymisierung ist komplex, da PDFs strukturierte
     * Dokumente sind. Diese Implementierung:
     * 1. Extrahiert Text
     * 2. Ersetzt PII im Text
     * 3. Erzeugt ein neues "Text-Report" PDF mit pseudonymisiertem Inhalt
     * 
     * Für echte In-Place-Pseudonymisierung wäre ein komplexer PDF-Editor nötig.
     */
    public function pseudonymize(string $filePath, array $confirmedRules): string
    {
        $text = $this->extractText($filePath);
        
        // PII im Text ersetzen
        $pseudonymizedText = $this->replacePiiInText($text, $confirmedRules);
        
        // Als TXT-Datei speichern (PDF-Struktur-Erhaltung ist komplex)
        $tempPath = tempnam(sys_get_temp_dir(), 'datadachs_pdf_') . '.txt';
        file_put_contents($tempPath, $pseudonymizedText);
        
        return $tempPath;
    }
    
    /**
     * Extrahiert Text aus PDF
     */
    private function extractText(string $filePath): string
    {
        // Versuche pdftotext (poppler-utils)
        $pdftotext = shell_exec('which pdftotext 2>/dev/null');
        if ($pdftotext) {
            $output = [];
            $returnCode = 0;
            exec('pdftotext -layout ' . escapeshellarg($filePath) . ' -', $output, $returnCode);
            if ($returnCode === 0) {
                return implode("\n", $output);
            }
        }
        
        // Fallback: PHP-basierte Extraktion (einfach, nicht perfekt)
        return $this->extractTextPhp($filePath);
    }
    
    /**
     * Einfache PHP-basierte PDF-Text-Extraktion
     * Extrahiert Text-Strings aus dem PDF-Roh-Format
     */
    private function extractTextPhp(string $filePath): string
    {
        $content = file_get_contents($filePath);
        $text = '';
        
        // Suche nach Text-Strings in PDF
        // PDF kodiert Text oft als (Text) oder <hex>
        if (preg_match_all('/\(([^)]+)\)/', $content, $matches)) {
            foreach ($matches[1] as $match) {
                // PDF-Escape-Sequenzen dekodieren
                $decoded = str_replace(
                    ['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'],
                    ["\n", "\r", "\t", '(', ')', '\\'],
                    $match
                );
                if (strlen($decoded) > 2 && preg_match('/[a-zA-Z]/', $decoded)) {
                    $text .= $decoded . ' ';
                }
            }
        }
        
        // Alternative: Stream-Objekte
        if (empty($text)) {
            // Suche nach Text in Content-Streams
            if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $streams)) {
                foreach ($streams[1] as $stream) {
                    // Entferne Binärdaten, behalte lesbaren Text
                    $cleaned = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $stream);
                    if (strlen($cleaned) > 10) {
                        $text .= $cleaned . ' ';
                    }
                }
            }
        }
        
        return $text;
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
    
    /**
     * Prüft ob pdftotext verfügbar ist
     */
    public static function isPdftotextAvailable(): bool
    {
        return shell_exec('which pdftotext 2>/dev/null') !== null;
    }
}
