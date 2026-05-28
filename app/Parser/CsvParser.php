<?php
/**
 * DataDachs – CSV Parser
 * Streaming-Verarbeitung mit Header-Erkennung
 */

namespace DataDachs\Parser;

use DataDachs\Service\FakerEngine;
use DataDachs\Service\PiiDetector;

class CsvParser
{
    private PiiDetector $detector;
    private FakerEngine $faker;
    private array $columnRules = [];
    private string $delimiter = ',';
    private string $enclosure = '"';
    private string $escape = '\\';
    
    public function __construct(PiiDetector $detector, FakerEngine $faker)
    {
        $this->detector = $detector;
        $this->faker = $faker;
    }
    
    /**
     * Analysiert CSV-Header und erkennt PII-Spalten
     */
    public function analyze(string $csvContent): array
    {
        $lines = explode("\n", $csvContent);
        $headerLine = $lines[0] ?? '';
        
        // Delimiter erkennen
        $this->delimiter = $this->detectDelimiter($headerLine);
        
        // Header parsen
        $header = str_getcsv($headerLine, $this->delimiter, $this->enclosure, $this->escape);
        
        return [
            'columns' => $header,
            'detected' => $this->detector->detectColumns('csv', $header),
            'delimiter' => $this->delimiter,
            'sample' => array_slice($lines, 1, 3),
        ];
    }
    
    /**
     * Pseudonymisiert CSV basierend auf bestätigten Regeln
     */
    public function pseudonymize(string $csvContent, array $confirmedRules): string
    {
        $this->columnRules = $confirmedRules;
        
        $lines = explode("\n", $csvContent);
        $result = [];
        
        // Header
        $headerLine = array_shift($lines);
        $result[] = $headerLine;
        
        // Datenzeilen
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $result[] = $this->processLine($line);
        }
        
        return implode("\n", $result);
    }
    
    /**
     * Verarbeitet eine einzelne CSV-Zeile
     */
    private function processLine(string $line): string
    {
        $values = str_getcsv($line, $this->delimiter, $this->enclosure, $this->escape);
        $newValues = [];
        
        foreach ($values as $index => $value) {
            $column = array_keys($this->columnRules)[$index] ?? null;
            $newValues[] = $this->processValue($value, $column);
        }
        
        // Rekonstruieren mit fputcsv-Logik
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $newValues, $this->delimiter, $this->enclosure, $this->escape);
        rewind($output);
        $csvLine = stream_get_contents($output);
        fclose($output);
        
        return rtrim($csvLine, "\n\r");
    }
    
    /**
     * Verarbeitet einen einzelnen Wert
     */
    private function processValue(string $value, ?string $column): string
    {
        if ($column && isset($this->columnRules[$column])) {
            $rule = $this->columnRules[$column];
            if ($rule['action'] === 'pseudonymize' && $rule['faker_method']) {
                return $this->faker->fake($rule['type'], $value);
            }
        }
        return $value;
    }
    
    /**
     * Erkennt den Delimiter aus der Header-Zeile
     */
    private function detectDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $counts = [];
        
        foreach ($delimiters as $delim) {
            $counts[$delim] = substr_count($line, $delim);
        }
        
        arsort($counts);
        return array_key_first($counts) ?: ',';
    }
}
