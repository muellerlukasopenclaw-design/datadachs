<?php
/**
 * DataDachs – Performance Profiler
 * Misst und loggt Performance-Metriken
 */

namespace DataDachs\Service;

class PerformanceProfiler
{
    private array $timers = [];
    private array $memory = [];
    private bool $enabled;
    
    public function __construct(bool $enabled = false)
    {
        $this->enabled = $enabled;
    }
    
    /**
     * Startet einen Timer
     */
    public function start(string $name): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $this->timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true),
        ];
    }
    
    /**
     * Stoppt einen Timer
     */
    public function stop(string $name): array
    {
        if (!$this->enabled || !isset($this->timers[$name])) {
            return ['duration' => 0, 'memory' => 0];
        }
        
        $timer = $this->timers[$name];
        $duration = microtime(true) - $timer['start'];
        $memory = memory_get_usage(true) - $timer['memory_start'];
        
        $result = [
            'name' => $name,
            'duration_ms' => round($duration * 1000, 2),
            'memory_mb' => round($memory / 1024 / 1024, 2),
        ];
        
        $this->timers[$name]['result'] = $result;
        
        return $result;
    }
    
    /**
     * Gibt alle Ergebnisse zurück
     */
    public function getResults(): array
    {
        $results = [];
        foreach ($this->timers as $name => $timer) {
            if (isset($timer['result'])) {
                $results[] = $timer['result'];
            }
        }
        return $results;
    }
    
    /**
     * Formatierte Ausgabe
     */
    public function formatResults(): string
    {
        $lines = [];
        $lines[] = "=== Performance Report ===";
        
        foreach ($this->getResults() as $result) {
            $lines[] = sprintf(
                "%-30s %8.2f ms %8.2f MB",
                $result['name'],
                $result['duration_ms'],
                $result['memory_mb']
            );
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Gesamtdauer
     */
    public function getTotalDuration(): float
    {
        $total = 0;
        foreach ($this->getResults() as $result) {
            $total += $result['duration_ms'];
        }
        return $total;
    }
    
    /**
     * Gesamtspeicher
     */
    public function getTotalMemory(): float
    {
        $total = 0;
        foreach ($this->getResults() as $result) {
            $total += $result['memory_mb'];
        }
        return $total;
    }
    
    /**
     * Reset
     */
    public function reset(): void
    {
        $this->timers = [];
        $this->memory = [];
    }
}
