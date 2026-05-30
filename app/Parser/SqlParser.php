<?php
/**
 * DataDachs – SQL Parser
 * Parst INSERT INTO ... VALUES und bearbeitet nur Datenwerte
 */

namespace DataDachs\Parser;

use DataDachs\Service\FakerEngine;
use DataDachs\Service\PiiDetector;
use DataDachs\Service\PreserveRuleService;

class SqlParser
{
    private PiiDetector $detector;
    private FakerEngine $faker;
    private ?PreserveRuleService $preserveService;
    private array $columnRules = [];
    private string $currentTable = '';
    
    public function __construct(PiiDetector $detector, FakerEngine $faker, ?PreserveRuleService $preserveService = null)
    {
        $this->detector = $detector;
        $this->faker = $faker;
        $this->preserveService = $preserveService;
    }
    
    /**
     * Analysiert SQL und erkennt PII-Spalten (für Review)
     */
    public function analyze(string $sql): array
    {
        $tables = [];
        $lines = explode("\n", $sql);
        $buffer = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Kommentare und Leerzeilen überspringen
            if (empty($line) || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
                continue;
            }
            
            $buffer .= ' ' . $line;
            
            // Vollständiges Statement erkennen (endet mit ;)
            if (substr($line, -1) === ';') {
                if (stripos($buffer, 'INSERT INTO') !== false) {
                    $tableInfo = $this->parseInsertLine($buffer);
                    if ($tableInfo) {
                        $tables[$tableInfo['table']] = [
                            'columns' => $tableInfo['columns'],
                            'detected' => $this->detector->detectColumns(
                                $tableInfo['table'],
                                $tableInfo['columns']
                            ),
                        ];
                    }
                }
                $buffer = '';
            }
        }
        
        return $tables;
    }
    
    /**
     * Pseudonymisiert SQL-Dump basierend auf bestätigten Regeln
     */
    public function pseudonymize(string $sql, array $confirmedRules): string
    {
        $this->columnRules = $confirmedRules;
        $lines = explode("\n", $sql);
        $result = [];
        $buffer = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            // Kommentare und Leerzeilen beibehalten
            if (empty($line) || strpos($line, '--') === 0) {
                $result[] = $line;
                continue;
            }
            
            $buffer .= ' ' . $line;
            
            // Vollständiges Statement erkennen (endet mit ;)
            if (substr($line, -1) === ';') {
                if (stripos($buffer, 'INSERT INTO') !== false) {
                    $result[] = $this->processInsertLine($buffer);
                } else {
                    $result[] = trim($buffer);
                }
                $buffer = '';
            }
        }
        
        // Restlicher Buffer (falls kein Semikolon am Ende)
        if (!empty($buffer)) {
            $result[] = trim($buffer);
        }
        
        return implode("\n", $result);
    }
    
    /**
     * Parst eine INSERT-Zeile in Tabellenname, Spalten und Werte
     */
    private function parseInsertLine(string $line): ?array
    {
        // INSERT INTO table (col1, col2) VALUES (...);
        // INSERT INTO table VALUES (...);
        if (!preg_match('/INSERT\s+INTO\s+`?(\w+)`?\s*(?:\(([^)]+)\))?\s*VALUES\s*(.+);?/i', $line, $matches)) {
            return null;
        }
        
        $table = $matches[1];
        $columns = [];
        
        if (isset($matches[2])) {
            $columns = array_map(
                fn($c) => trim($c, " \t\n\r\0\x0B`\"'"),
                explode(',', $matches[2])
            );
        }
        
        return [
            'table' => $table,
            'columns' => $columns,
        ];
    }
    
    /**
     * Verarbeitet eine INSERT-Zeile und ersetzt Werte
     */
    private function processInsertLine(string $line): string
    {
        $tableInfo = $this->parseInsertLine($line);
        if (!$tableInfo) {
            return $line;
        }
        
        $this->currentTable = $tableInfo['table'];
        $columns = $tableInfo['columns'];
        
        // Regeln für diese Tabelle holen
        $tableRules = $this->columnRules[$tableInfo['table']] ?? [];
        
        // VALUES-Teil extrahieren
        if (!preg_match('/VALUES\s+(.+);?$/i', $line, $valMatch)) {
            return $line;
        }
        
        $valuesPart = $valMatch[1];
        
        // Multi-Row: VALUES (...), (...), (...)
        $rows = $this->splitValueGroups($valuesPart);
        $newRows = [];
        
        foreach ($rows as $row) {
            $newRows[] = $this->processValueRow($row, $columns, $tableRules);
        }
        
        // Zeile rekonstruieren
        $prefix = preg_replace('/VALUES\s+.+;?$/i', 'VALUES ', $line);
        return rtrim($prefix) . ' ' . implode(', ', $newRows) . ';';
    }
    
    /**
     * Teilt VALUES (...), (...) in einzelne Gruppen
     */
    private function splitValueGroups(string $valuesPart): array
    {
        $groups = [];
        $depth = 0;
        $current = '';
        
        for ($i = 0; $i < strlen($valuesPart); $i++) {
            $char = $valuesPart[$i];
            
            if ($char === '(') {
                if ($depth === 0) {
                    $current = '';
                }
                $depth++;
            }
            
            if ($depth > 0) {
                $current .= $char;
            }
            
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $groups[] = $current;
                    $current = '';
                }
            }
        }
        
        return $groups;
    }
    
    /**
     * Verarbeitet eine einzelne Werte-Gruppe (row)
     */
    private function processValueRow(string $row, array $columns, array $tableRules): string
    {
        // Klammern entfernen
        $inner = trim($row, '()');
        
        // Werte parsen (Quotes, Escaping, NULL beachten)
        $values = $this->parseValues($inner);
        $newValues = [];
        
        foreach ($values as $index => $value) {
            $column = $columns[$index] ?? null;
            $newValues[] = $this->processValue($value, $column, $tableRules);
        }
        
        return '(' . implode(', ', $newValues) . ')';
    }
    
    /**
     * Parst einzelne Werte aus einer Werte-Liste (Quotes, Escaping, NULL)
     */
    private function parseValues(string $inner): array
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        $escaped = false;
        
        for ($i = 0; $i < strlen($inner); $i++) {
            $char = $inner[$i];
            
            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }
            
            if ($char === '\\') {
                $current .= $char;
                $escaped = true;
                continue;
            }
            
            if (!$inQuotes && ($char === "'" || $char === '"')) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
                continue;
            }
            
            if ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $current .= $char;
                continue;
            }
            
            if (!$inQuotes && $char === ',') {
                $values[] = trim($current);
                $current = '';
                continue;
            }
            
            $current .= $char;
        }
        
        if ($current !== '') {
            $values[] = trim($current);
        }
        
        return $values;
    }
    
    /**
     * Verarbeitet einen einzelnen Wert (pseudonymisieren oder beibehalten)
     */
    private function processValue(string $value, ?string $column, array $tableRules): string
    {
        // NULL, Zahlen, Funktionen beibehalten
        if (strtoupper($value) === 'NULL') {
            return 'NULL';
        }
        
        if (is_numeric($value)) {
            return $value;
        }
        
        // Prüfen ob Spalte pseudonymisiert werden soll
        if ($column && isset($tableRules[$column])) {
            $rule = $tableRules[$column];
            if ($rule['action'] === 'pseudonymize' && isset($rule['type'])) {
                $unquoted = $this->unquote($value);
                
                // Preserve Rules prüfen
                if ($this->preserveService && $this->preserveService->shouldPreserve($unquoted)) {
                    return $value;
                }
                
                $fake = $this->faker->fake($rule['type'], $unquoted);
                return $this->quote($fake, $value);
            }
        }
        
        return $value;
    }
    
    /**
     * Entfernt Quotes vom Wert
     */
    private function unquote(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === "'" || $first === '"') && $first === $last) {
                // Escaped quotes behandeln
                $inner = substr($value, 1, -1);
                return str_replace('\\' . $first, $first, $inner);
            }
        }
        return $value;
    }
    
    /**
     * Fügt Quotes wieder hinzu (im Original-Format)
     */
    private function quote(string $value, string $original): string
    {
        if (strlen($original) >= 2) {
            $quote = $original[0];
            if ($quote === "'" || $quote === '"') {
                $escaped = str_replace($quote, '\\' . $quote, $value);
                return $quote . $escaped . $quote;
            }
        }
        return $value;
    }
}
