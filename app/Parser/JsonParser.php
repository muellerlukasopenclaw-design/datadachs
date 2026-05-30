<?php
/**
 * DataDachs – JSON Parser
 * Rekursive Key-/Value-Erkennung mit Strukturerhalt
 */

namespace DataDachs\Parser;

use DataDachs\Service\FakerEngine;
use DataDachs\Service\PiiDetector;
use DataDachs\Service\PreserveRuleService;

class JsonParser
{
    private PiiDetector $detector;
    private FakerEngine $faker;
    private ?PreserveRuleService $preserveService;
    private array $keyRules = [];
    private int $maxDepth = 20;
    
    public function __construct(PiiDetector $detector, FakerEngine $faker, ?PreserveRuleService $preserveService = null)
    {
        $this->detector = $detector;
        $this->faker = $faker;
        $this->preserveService = $preserveService;
    }
    
    /**
     * Analysiert JSON und erkennt PII-Keys
     */
    public function analyze(string $jsonContent): array
    {
        $data = json_decode($jsonContent, true);
        if ($data === null) {
            throw new \InvalidArgumentException('Ungültiges JSON');
        }
        
        $keys = $this->collectKeys($data);
        
        return [
            'keys' => array_values(array_unique($keys)),
            'detected' => $this->detector->detectColumns('json', array_values(array_unique($keys))),
            'structure' => $this->getStructurePreview($data),
        ];
    }
    
    /**
     * Pseudonymisiert JSON basierend auf bestätigten Regeln
     */
    public function pseudonymize(string $jsonContent, array $confirmedRules): string
    {
        $this->keyRules = $confirmedRules;
        
        $data = json_decode($jsonContent, true);
        if ($data === null) {
            throw new \InvalidArgumentException('Ungültiges JSON');
        }
        
        $this->processArray($data);
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Sammelt alle Keys rekursiv
     */
    private function collectKeys(array $data, string $prefix = ''): array
    {
        $keys = [];
        
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "$prefix.$key" : $key;
            $keys[] = $key; // Nur der Key-Name, nicht der Pfad
            
            if (is_array($value) && !$this->isAssociative($value)) {
                // Array von Objekten – nur erstes Element scannen
                if (isset($value[0]) && is_array($value[0])) {
                    $keys = array_merge($keys, $this->collectKeys($value[0], $fullKey));
                }
            } elseif (is_array($value)) {
                $keys = array_merge($keys, $this->collectKeys($value, $fullKey));
            }
        }
        
        return $keys;
    }
    
    /**
     * Verarbeitet Array rekursiv
     */
    private function processArray(array &$data): void
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                if (!$this->isAssociative($value) && isset($value[0]) && is_array($value[0])) {
                    // Array von Objekten – alle Elemente verarbeiten
                    foreach ($value as &$item) {
                        $this->processArray($item);
                    }
                } else {
                    $this->processArray($value);
                }
            } elseif (is_string($value) || is_numeric($value)) {
                // Prüfen ob Key pseudonymisiert werden soll
                if (isset($this->keyRules[$key])) {
                    $rule = $this->keyRules[$key];
                    if ($rule['action'] === 'pseudonymize' && $rule['faker_method']) {
                        // Preserve Rules prüfen
                        if ($this->preserveService && $this->preserveService->shouldPreserve((string) $value)) {
                            continue;
                        }
                        $value = $this->faker->fake($rule['type'], (string) $value);
                    }
                }
            }
        }
    }
    
    /**
     * Prüft ob Array assoziativ ist
     */
    private function isAssociative(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
    
    /**
     * Erzeugt eine Struktur-Vorschau
     */
    private function getStructurePreview(array $data, int $depth = 0): array
    {
        if ($depth > 3) {
            return ['...'];
        }
        
        $preview = [];
        $count = 0;
        
        foreach ($data as $key => $value) {
            if ($count >= 5) {
                $preview['...'] = '...';
                break;
            }
            
            if (is_array($value)) {
                if (!$this->isAssociative($value) && isset($value[0])) {
                    $preview[$key] = ['[array mit ' . count($value) . ' Elementen]'];
                } else {
                    $preview[$key] = $this->getStructurePreview($value, $depth + 1);
                }
            } else {
                $preview[$key] = is_string($value) 
                    ? substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '')
                    : $value;
            }
            $count++;
        }
        
        return $preview;
    }
}
