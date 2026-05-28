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
    private ?PerformanceProfiler $profiler = null;
    
    public function __construct(
        DatabaseService $dbService,
        PiiDetector $detector,
        FakerEngine $faker,
        ?PreserveRuleService $preserveService = null,
        int $batchSize = 1000,
        ?PerformanceProfiler $profiler = null
    ) {
        $this->dbService = $dbService;
        $this->detector = $detector;
        $this->faker = $faker;
        $this->preserveService = $preserveService;
        $this->batchSize = $batchSize;
        $this->profiler = $profiler;
    }
    
    /**
     * Analysiert alle Tabellen und erkennt PII-Spalten
     */
    public function analyzeSchema(): array
    {
        $this->profiler?->start('analyze_schema');
        
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
        
        $this->profiler?->stop('analyze_schema');
        
        return $result;
    }
    
    /**
     * Pseudonymisiert eine Tabelle basierend auf bestätigten Regeln
     * Optimiert: Prepared Statements, Bulk-Updates, Transaktionen
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
        
        // Transaktion starten (Performance)
        $pdo = $this->dbService->getPdo();
        $pdo->beginTransaction();
        
        try {
            // Prepared Statement für UPDATE vorbereiten
            $updateStmt = $this->prepareUpdateStatement($table, $targetColumns, $primaryKeys);
            
            $this->profiler?->start('pseudonymize_' . $table);
            
            // Batch-Processing
            $offset = 0;
            while ($offset < $totalRows) {
                $this->profiler?->start('fetch_batch_' . $offset);
                $rows = $this->dbService->fetchBatch($table, $columnNames, $offset, $this->batchSize);
                $this->profiler?->stop('fetch_batch_' . $offset);
                
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
                    
                    // WHERE aus Primärschlüssel
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
                            // Dynamisches Statement pro Zeile (weil unterschiedliche Spalten NULL sein können)
                            $dynamicStmt = $this->buildDynamicUpdateStatement($table, array_keys($updateData), array_keys($whereData));
                            $params = array_merge(array_values($updateData), array_values($whereData));
                            $dynamicStmt->execute($params);
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
                
                // Memory-Limit beachten: Zwischencommit alle 10.000 Zeilen
                if ($processed % 10000 === 0) {
                    $pdo->commit();
                    $pdo->beginTransaction();
                }
            }
            
            $pdo->commit();
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
        $this->profiler?->stop('pseudonymize_' . $table);
        
        return [
            'table' => $table,
            'processed' => $processed,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }
    
    /**
     * Bereitet ein Prepared Statement für Updates vor
     */
    private function prepareUpdateStatement(string $table, array $targetColumns, array $primaryKeys): \PDOStatement
    {
        $pdo = $this->dbService->getPdo();
        
        $sets = [];
        foreach ($targetColumns as $col) {
            $sets[] = "\"{$col}\" = ?";
        }
        
        $wheres = [];
        if (!empty($primaryKeys)) {
            foreach ($primaryKeys as $pk) {
                $wheres[] = "\"{$pk}\" = ?";
            }
        } else {
            // Fallback: alle Spalten
            $columns = $this->dbService->getColumns($table);
            foreach ($columns as $col) {
                $wheres[] = "\"{$col['name']}\" = ?";
            }
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE " . implode(' AND ', $wheres);
        return $pdo->prepare($sql);
    }
    
    /**
     * Erstellt ein dynamisches Update-Statement für eine Zeile
     */
    private function buildDynamicUpdateStatement(string $table, array $updateColumns, array $whereColumns): \PDOStatement
    {
        $pdo = $this->dbService->getPdo();
        
        $sets = [];
        foreach ($updateColumns as $col) {
            $sets[] = "\"{$col}\" = ?";
        }
        
        $wheres = [];
        foreach ($whereColumns as $col) {
            $wheres[] = "\"{$col}\" = ?";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE " . implode(' AND ', $wheres);
        return $pdo->prepare($sql);
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
     * Optimiert: Streaming, Prepared Statements
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
        
        // INSERTs in Batches (Multi-Row für bessere Performance)
        $offset = 0;
        $batchValues = [];
        
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
                
                $batchValues[] = "(" . implode(', ', $values) . ")";
                
                // Multi-Row INSERT: alle 1000 Zeilen
                if (count($batchValues) >= 1000) {
                    $output[] = "INSERT INTO {$table} (" . implode(', ', $columnNames) . ") VALUES ";
                    $output[] = implode(",\n", $batchValues) . ";";
                    $batchValues = [];
                }
            }
            
            $offset += $this->batchSize;
        }
        
        // Restliche Werte
        if (!empty($batchValues)) {
            $output[] = "INSERT INTO {$table} (" . implode(', ', $columnNames) . ") VALUES ";
            $output[] = implode(",\n", $batchValues) . ";";
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
