<?php
/**
 * DataDachs – TXT Parser
 * Regex-basierte Freitext-Erkennung und Ersetzung
 */

namespace DataDachs\Parser;

use DataDachs\Service\FakerEngine;
use DataDachs\Service\PreserveRuleService;

class TxtParser
{
    private FakerEngine $faker;
    private array $patterns;
    private ?PreserveRuleService $preserveService;
    
    public function __construct(FakerEngine $faker, ?PreserveRuleService $preserveService = null)
    {
        $this->faker = $faker;
        $this->preserveService = $preserveService;
        $rules = require __DIR__ . '/../../config/pii-rules.php';
        $this->patterns = $rules['regex_patterns'] ?? [];
    }
    
    /**
     * Analysiert Text und erkennt PII-Muster
     */
    public function analyze(string $content): array
    {
        $findings = [];
        
        foreach ($this->patterns as $type => $config) {
            if (preg_match_all($config['pattern'], $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $findings[] = [
                        'type' => $type,
                        'value' => $match[0],
                        'position' => $match[1],
                        'score' => $config['weight'],
                    ];
                }
            }
        }
        
        // Nach Position sortieren
        usort($findings, fn($a, $b) => $a['position'] <=> $b['position']);
        
        return [
            'findings' => $findings,
            'sample' => substr($content, 0, 500),
        ];
    }
    
    /**
     * Pseudonymisiert Text – ersetzt erkannte Muster
     */
    public function pseudonymize(string $content, array $enabledTypes): string
    {
        // Sortiere nach Position (rückwärts, damit Offsets stabil bleiben)
        $replacements = [];
        
        foreach ($enabledTypes as $type => $enabled) {
            if (!$enabled || !isset($this->patterns[$type])) {
                continue;
            }
            
            $pattern = $this->patterns[$type]['pattern'];
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $replacements[] = [
                        'start' => $match[1],
                        'end' => $match[1] + strlen($match[0]),
                        'original' => $match[0],
                        'type' => $type,
                    ];
                }
            }
        }
        
        // Nach Position sortieren, rückwärts ersetzen
        usort($replacements, fn($a, $b) => $b['start'] <=> $a['start']);
        
        // Überlappungen entfernen (spätere ersetzen frühere)
        $seen = [];
        $filtered = [];
        foreach ($replacements as $r) {
            $overlap = false;
            foreach ($seen as $s) {
                if ($r['start'] < $s['end'] && $r['end'] > $s['start']) {
                    $overlap = true;
                    break;
                }
            }
            if (!$overlap) {
                $seen[] = $r;
                $filtered[] = $r;
            }
        }
        
        // Ersetzen
        foreach ($filtered as $r) {
            // Prüfe Preserve Rules
            if ($this->preserveService && $this->preserveService->shouldPreserve($r['original'])) {
                continue;
            }
            $fake = $this->generateFake($r['type'], $r['original']);
            $content = substr_replace($content, $fake, $r['start'], strlen($r['original']));
        }
        
        return $content;
    }
    
    /**
     * Generiert einen Fake-Wert für einen Regex-Typ
     */
    private function generateFake(string $type, string $original): string
    {
        return match ($type) {
            'email' => $this->faker->fake('email', $original),
            'phone_de' => $this->faker->fake('phone', $original),
            'iban' => $this->faker->fake('iban', $original),
            'ipv4' => $this->faker->fake('ip', $original),
            'ipv6' => $this->faker->fake('ip', $original),
            'url' => $this->faker->fake('url', $original),
            'uuid' => $this->faker->fake('uuid', $original),
            'postcode_de' => $this->faker->fake('postcode', $original),
            'date_iso', 'date_de' => $this->faker->fake('birthdate', $original),
            default => $this->faker->fake('word', $original),
        };
    }
}
