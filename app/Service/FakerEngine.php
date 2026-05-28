<?php
/**
 * DataDachs – Faker Engine
 * Erzeugt realistische Ersatzwerte mit Konsistenz-Mapping
 */

namespace DataDachs\Service;

use Faker\Factory;

class FakerEngine
{
    private \Faker\Generator $faker;
    private array $mapping = [];      // original -> fake (pro Job)
    private array $rules;
    private ?string $deterministicSeed;
    private ?PreserveRuleService $preserveService;
    private array $valueCache = [];   // Cache für häufige Werte
    private int $cacheSize = 10000;   // Max Cache-Einträge
    private int $cacheHits = 0;
    private int $cacheMisses = 0;
    
    public function __construct(?string $seed = null, ?PreserveRuleService $preserveService = null)
    {
        $this->rules = require __DIR__ . '/../../config/pii-rules.php';
        $this->faker = Factory::create($this->rules['faker_locale'] ?? 'de_DE');
        $this->deterministicSeed = $seed;
        $this->preserveService = $preserveService;
        
        if ($seed) {
            $this->faker->seed(crc32($seed));
        }
    }
    
    /**
     * Erzeugt einen Fake-Wert für einen gegebenen Typ
     * Gleiche Originalwerte liefern konsistent denselben Fake-Wert
     * Preserve-Regeln werden berücksichtigt (Ausnahmewerte nicht pseudonymisieren)
     * 
     * Optimiert mit LRU-Cache für große Datensätze
     */
    public function fake(string $type, string $original): string
    {
        // Preserve-Check: Wert in Ausnahmeliste?
        if ($this->preserveService && $this->preserveService->shouldPreserve($original)) {
            return $original;
        }
        
        $cacheKey = $type . '|' . $original;
        
        // Schneller Pfad: Mapping-Cache (pro Job)
        if (isset($this->mapping[$cacheKey])) {
            return $this->mapping[$cacheKey];
        }
        
        // Schneller Pfad: Value-Cache (LRU, begrenzt)
        if (isset($this->valueCache[$cacheKey])) {
            $this->cacheHits++;
            return $this->valueCache[$cacheKey];
        }
        
        $this->cacheMisses++;
        
        $fake = $this->generate($type, $original);
        
        // In beide Caches speichern
        $this->mapping[$cacheKey] = $fake;
        
        // LRU-Cache: Alte Einträge entfernen wenn voll
        if (count($this->valueCache) >= $this->cacheSize) {
            // Entferne ~10% der ältesten Einträge
            $toRemove = (int) ($this->cacheSize * 0.1);
            $keys = array_keys($this->valueCache);
            for ($i = 0; $i < $toRemove; $i++) {
                unset($this->valueCache[$keys[$i]]);
            }
        }
        $this->valueCache[$cacheKey] = $fake;
        
        return $fake;
    }
    
    /**
     * Generiert einen neuen Fake-Wert (ohne Caching)
     */
    private function generate(string $type, string $original): string
    {
        return match ($type) {
            'first_name'     => $this->faker->firstName(),
            'last_name'      => $this->faker->lastName(),
            'full_name'      => $this->faker->name(),
            'email'          => $this->safeEmail(),
            'phone'          => $this->faker->phoneNumber(),
            'street'         => $this->faker->streetName(),
            'house_number'   => $this->faker->buildingNumber(),
            'postcode'       => $this->faker->postcode(),
            'city'           => $this->faker->city(),
            'country'        => $this->faker->country(),
            'birthdate'      => $this->shiftDate($original),
            'age'            => (string) $this->faker->numberBetween(18, 80),
            'iban'           => $this->faker->iban(),
            'bic'            => $this->faker->swiftBicNumber(),
            'bank'           => $this->faker->company(),
            'company'        => $this->faker->company(),
            'username'       => $this->faker->userName(),
            'password'       => $this->faker->password(12, 20),
            'ip'             => $this->faker->ipv4(),
            'mac'            => $this->faker->macAddress(),
            'domain'         => $this->faker->domainName(),
            'url'            => $this->faker->url(),
            'ssn'            => $this->faker->randomNumber(9, true),
            'tax_id'         => $this->faker->randomNumber(11, true),
            'uuid'           => $this->faker->uuid(),
            default          => $this->faker->word(),
        };
    }
    
    /**
     * E-Mail mit sicherer Domain
     */
    private function safeEmail(): string
    {
        $domains = $this->rules['safe_email_domains'] ?? ['example.test'];
        $domain = $this->faker->randomElement($domains);
        $local = strtolower($this->faker->firstName() . '.' . $this->faker->lastName());
        $local = preg_replace('/[^a-z0-9._-]/', '', $local);
        return $local . '@' . $domain;
    }
    
    /**
     * Verschiebt ein Datum um zufällige Tage (Erhalt der Altersstruktur)
     */
    private function shiftDate(string $original): string
    {
        $timestamp = strtotime($original);
        if ($timestamp === false) {
            return $this->faker->date('Y-m-d');
        }
        
        // Verschiebe um 30-365 Tage zufällig
        $shift = $this->faker->numberBetween(30, 365) * ($this->faker->boolean() ? 1 : -1);
        $newTimestamp = strtotime("$shift days", $timestamp);
        
        // Format erhalten (erkennen aus Original)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $original)) {
            return date('Y-m-d', $newTimestamp);
        }
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $original)) {
            return date('d.m.Y', $newTimestamp);
        }
        return date('Y-m-d', $newTimestamp);
    }
    
    /**
     * Gibt das Mapping zurück (für Audit/Logging)
     */
    public function getMapping(): array
    {
        return $this->mapping;
    }
    
    /**
     * Löscht das Mapping (Datenschutz)
     */
    public function clearMapping(): void
    {
        $this->mapping = [];
        $this->valueCache = [];
        $this->cacheHits = 0;
        $this->cacheMisses = 0;
    }
    
    /**
     * Cache-Statistiken
     */
    public function getCacheStats(): array
    {
        $total = $this->cacheHits + $this->cacheMisses;
        return [
            'hits' => $this->cacheHits,
            'misses' => $this->cacheMisses,
            'total' => $total,
            'hit_rate' => $total > 0 ? round($this->cacheHits / $total * 100, 2) : 0,
            'mapping_size' => count($this->mapping),
            'value_cache_size' => count($this->valueCache),
        ];
    }
    
    /**
     * Lädt ein Mapping (für reproduzierbare Pseudonymisierung)
     */
    public function loadMapping(array $mapping): void
    {
        $this->mapping = $mapping;
    }
}
