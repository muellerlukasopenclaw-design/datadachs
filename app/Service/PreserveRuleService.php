<?php

declare(strict_types=1);

namespace DataDachs\Service;

/**
 * Service for managing preserve/allowlist rules.
 * Values in the preserve list are never pseudonymized.
 */
class PreserveRuleService
{
    private array $preserveRules = [];
    private bool $caseSensitive;

    public function __construct(?string $rulesJson = null, bool $caseSensitive = false)
    {
        $this->caseSensitive = $caseSensitive;
        $this->loadRules($rulesJson);
    }

    /**
     * Load preserve rules from JSON string or comma-separated list.
     */
    public function loadRules(?string $rulesJson): void
    {
        $this->preserveRules = [];
        
        if (empty($rulesJson)) {
            return;
        }

        $trimmed = trim($rulesJson);
        
        // Try JSON first
        if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $this->preserveRules = $this->flattenRules($decoded);
                return;
            }
        }

        // Fallback: comma-separated or newline-separated
        $separators = [',', "\n", "\r\n"];
        $parts = [$trimmed];
        
        foreach ($separators as $sep) {
            $newParts = [];
            foreach ($parts as $part) {
                $newParts = array_merge($newParts, explode($sep, $part));
            }
            $parts = $newParts;
        }

        foreach ($parts as $part) {
            $cleaned = trim($part);
            if ($cleaned !== '') {
                $this->preserveRules[] = $cleaned;
            }
        }
    }

    /**
     * Flatten nested rule arrays to a simple list of strings.
     */
    private function flattenRules(array $rules): array
    {
        $flat = [];
        foreach ($rules as $key => $value) {
            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenRules($value));
            } elseif (is_string($value) && $value !== '') {
                $flat[] = $value;
            } elseif (is_int($key) && is_string($value)) {
                $flat[] = $value;
            }
        }
        return $flat;
    }

    /**
     * Check if a value should be preserved (not pseudonymized).
     */
    public function shouldPreserve(string $value): bool
    {
        if (empty($this->preserveRules)) {
            return false;
        }

        $checkValue = $this->caseSensitive ? $value : mb_strtolower($value);

        foreach ($this->preserveRules as $rule) {
            $ruleValue = $this->caseSensitive ? $rule : mb_strtolower($rule);
            
            // Exact match
            if ($checkValue === $ruleValue) {
                return true;
            }
            
            // Substring match (for partial preservation like "admin" matching "admin_user")
            if (str_contains($checkValue, $ruleValue) || str_contains($ruleValue, $checkValue)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all preserve rules.
     */
    public function getRules(): array
    {
        return $this->preserveRules;
    }

    /**
     * Add a single preserve rule.
     */
    public function addRule(string $rule): void
    {
        $cleaned = trim($rule);
        if ($cleaned !== '' && !in_array($cleaned, $this->preserveRules, true)) {
            $this->preserveRules[] = $cleaned;
        }
    }

    /**
     * Remove a preserve rule.
     */
    public function removeRule(string $rule): void
    {
        $this->preserveRules = array_values(
            array_filter(
                $this->preserveRules,
                fn($r) => $r !== $rule
            )
        );
    }

    /**
     * Get default preserve rules for common system values.
     */
    public static function getDefaultRules(): array
    {
        return [
            'admin',
            'administrator',
            'root',
            'system',
            'guest',
            'anonymous',
            'null',
            'test',
            'demo',
            'support',
            'noreply',
            'no-reply',
            'postmaster',
            'webmaster',
            'hostmaster',
            'abuse',
            'info',
            'sales',
            'marketing',
            'help',
            'contact',
        ];
    }

    /**
     * Create service with default rules.
     */
    public static function withDefaults(): self
    {
        $service = new self();
        foreach (self::getDefaultRules() as $rule) {
            $service->addRule($rule);
        }
        return $service;
    }
}
