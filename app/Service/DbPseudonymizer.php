<?php
/**
 * DataDachs – Database Pseudonymizer
 * Batch-Processing von DB-Zeilen mit PII-Erkennung + Faker
 */

namespace DataDachs\Service;

class DbPseudonymizer
{
    private DatabaseService $dbService;
    private PiiDetector $detector;
    private FakerEngine $faker;
    private ?PreserveRuleService $preserveService;
    private int $batchSize;
    private array $progress = [
        'total' => 0,
        'processed' => 0,
        'tables' => [],
    ];
    
    public function __construct(
        DatabaseService $dbService,
        PiiDetector $detector,
        FakerEngine $faker,
        ?PreserveRuleService $preserveService = null,
        int $batchSize = 1000
    ) {
        $this->dbService = $dbService;
        $this->detector = $detector;
        $this->faker = $faker;
        $this->preserveService = $preserveService;
        $this->batchSize = $batchSize;
    }
    
    /**
     * Analysiert alle Tabellen und erkennt PII-Spalten
     */
    public function analyzeSchema(): array
    {
        $tables = $this->dbService->getTables();
        $result = [];
        
        foreach ($tables as $table) {
            $columns = $this->dbService->getColumns($table);
            $columnNames = array_column($columns, 'name');
            
            $detected = $this->detector->detectColumns($table, $columnNames);
            
            // Nur Spalten mit Erkennung zurückgeben
            $piiColumns = [];
            foreach ($detected as $colName => $info) {
                if ($info['detected']) {
                    $piiColumns[$colName] = $info;
                }
            }
            
            if (!empty($piiColumns)) {
                $result[$table] = [
                    'columns' => $columns,
                    'detected' => $piiColumns,
                    'row_count' => $this->dbService->countRows($table),
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Pseudonymisiert eine Tabelle basierend auf bestätigten Regeln
     */
    public function pseudonymizeTable(string $table, array $columnRules, ?callable $progressCallback = null): array
    {
        $totalRows = $this->dbService->countRows($table);
        $processed = 0;
        $updated = 0;
        $errors = [];
        
        // Primärschlüssel holen
        $primaryKeys = $this->dbService->getPrimaryKeys($table);
        
        // Alle Spalten holen
        $allColumns = $this->dbService->getColumns($table);
        $columnNames = array_column($allColumns, 'name');
        
        // Nur Spalten die aktualisiert werden sollen
        $targetColumns = [];
        foreach ($columnRules as $col => $rule) {
            if (($rule['action'] ?? 'keep') === 'pseudonymize') {
                $targetColumns[] = $col;
            }
        }
        
        if (empty($targetColumns)) {
            return ['processed' => 0, 'updated' => 0, 'errors' => []];
        }
        
        // Batch-Processing
        $offset = 0;
        while ($offset < $totalRows) {
            $rows = $this->dbService->fetchBatch($table, $columnNames, $offset, $this->batchSize);
            
            foreach ($rows as $row) {
                $updateData = [];
                $whereData = [];
                
                foreach ($targetColumns as $col) {
                    $originalValue = $row[$col] ?? null;
                    
                    // NULL beibehalten
                    if ($originalValue === null) {
                        continue;
                    }
                    
                    $rule = $columnRules[$col];
                    $type = $rule['type'];
                    
                    // Preserve-Check
                    if ($this->preserveService && $this->preserveService->shouldPreserve((string) $originalValue)) {
                        continue;
                    }
                    
                    // Pseudonymisieren
                    $fakeValue = $this->faker->fake($type, (string) $originalValue);
                    $updateData[$col] = $fakeValue;
                }
                
                // WHERE aus Primärschlüssel oder allen Spalten
                if (!empty($primaryKeys)) {
                    foreach ($primaryKeys as $pk) {
                        $whereData[$pk] = $row[$pk];
                    }
                } else {
                    // Fallback: alle Spalten als WHERE
                    foreach ($row as $col => $val) {
                        $whereData[$col] = $val;
                    }
                }
                
                if (!empty($updateData)) {
                    try {
                        $this->dbService->updateRow($table, $updateData, $whereData);
                        $updated++;
                    } catch (\Exception $e) {
                        $errors[] = "Zeile {$processed}: " . $e->getMessage();
                    }
                }
                
                $processed++;
            }
            
            $offset += $this->batchSize;
            
            if ($progressCallback) {
                $progressCallback($table, $processed, $totalRows);
            }
        }
        
        return [
            'table' => $table,
            'processed' => $processed,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }
    
    /**
     * Pseudonymisiert mehrere Tabellen
     */
    public function pseudonymizeTables(array $tableRules, ?callable $progressCallback = null): array
    {
        $results = [];
        
        foreach ($tableRules as $table => $columnRules) {
            $results[$table] = $this->pseudonymizeTable($table, $columnRules, $progressCallback);
        }
        
        // Mapping löschen (Datenschutz)
        $this->faker->clearMapping();
        
        return $results;
    }
    
    /**
     * Exportiert pseudonymisierte Tabelle als SQL-Dump
     */
    public function exportAsSql(string $table, array $columnRules): string
    {
        $columns = $this->dbService->getColumns($table);
        $columnNames = array_column($columns, 'name');
        $totalRows = $this->dbService->countRows($table);
        
        $output = [];
        $output[] = "-- DataDachs Export: {$table}";
        $output[] = "-- Zeilen: {$totalRows}";
        $output[] = "-- Generiert: " . date('Y-m-d H:i:s');
        $output[] = "";
        
        // INSERTs in Batches
        $offset = 0;
        while ($offset < $totalRows) {
            $rows = $this->dbService->fetchBatch($table, $columnNames, $offset, $this->batchSize);
            
            foreach ($rows as $row) {
                $values = [];
                foreach ($columnNames as $col) {
                    $val = $row[$col] ?? null;
                    if ($val === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . addslashes((string) $val) . "'";
                    }
                }
                
                $output[] = "INSERT INTO {$table} (" . implode(', ', $columnNames) . ") VALUES (" . implode(', ', $values) . ");";
            }
            
            $offset += $this->batchSize;
        }
        
        return implode("\n", $output);
    }
    
    /**
     * Gibt aktuellen Fortschritt zurück
     */
    public function getProgress(): array
    {
        return $this->progress;
    }
}
