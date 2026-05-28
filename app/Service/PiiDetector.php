<?php
/**
 * DataDachs – PII Detector Service
 * Erkennt personenbezogene Daten per Spaltennamen + Regex + Kontext
 */

namespace DataDachs\Service;

class PiiDetector
{
    private array $rules;
    private array $tableContext;
    
    public function __construct()
    {
        $this->rules = require __DIR__ . '/../../config/pii-rules.php';
        $this->tableContext = $this->rules['table_context'] ?? [];
    }
    
    /**
     * Analysiert Spalten einer Tabelle und erkennt PII-Kandidaten
     */
    public function detectColumns(string $tableName, array $columnNames): array
    {
        $contextMultiplier = $this->tableContext[strtolower($tableName)] ?? 1.0;
        $results = [];
        
        foreach ($columnNames as $column) {
            $normalized = $this->normalizeColumnName($column);
            $rule = $this->rules['column_rules'][$normalized] ?? null;
            
            if ($rule) {
                $score = $rule['weight'] * $contextMultiplier;
                $results[$column] = [
                    'detected' => true,
                    'type' => $rule['type'],
                    'faker_method' => $rule['faker'],
                    'score' => round($score, 1),
                    'method' => 'column_name',
                    'action' => 'pseudonymize',
                ];
            } else {
                // Regex-Check auf Wertebene (nur wenn kein Spaltenname-Treffer)
                $results[$column] = [
                    'detected' => false,
                    'type' => null,
                    'faker_method' => null,
                    'score' => 0,
                    'method' => null,
                    'action' => 'keep',
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Erkennt PII in einem einzelnen Wert per Regex
     */
    public function detectValue(string $value): ?array
    {
        foreach ($this->rules['regex_patterns'] as $type => $config) {
            if (preg_match($config['pattern'], $value)) {
                return [
                    'type' => $type,
                    'score' => $config['weight'],
                    'method' => 'regex',
                ];
            }
        }
        return null;
    }
    
    /**
     * Normalisiert Spaltennamen für Regel-Lookup
     */
    private function normalizeColumnName(string $name): string
    {
        return strtolower(str_replace(['-', ' '], '_', $name));
    }
    
    /**
     * Gibt alle verfügbaren Regel-Typen zurück
     */
    public function getAvailableTypes(): array
    {
        $types = [];
        foreach ($this->rules['column_rules'] as $rule) {
            $types[$rule['type']] = $rule['faker'];
        }
        return $types;
    }
}
