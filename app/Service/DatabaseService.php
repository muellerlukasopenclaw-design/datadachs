<?php
/**
 * DataDachs – Database Service
 * Verbindung, Schema-Introspection, Batch-Queries für Datenbank-Modus
 */

namespace DataDachs\Service;

class DatabaseService
{
    private ?\PDO $pdo = null;
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    /**
     * Verbindet mit einer Datenbank
     */
    public function connect(string $dsn, ?string $user = null, ?string $password = null, array $options = []): bool
    {
        try {
            $defaultOptions = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new \PDO($dsn, $user, $password, array_merge($defaultOptions, $options));
            return true;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
        }
    }
    
    /**
     * Verbindet aus Konfiguration (ENV oder übergeben)
     */
    public function connectFromConfig(): bool
    {
        $driver = $this->config['driver'] ?? 'sqlite';
        
        return match ($driver) {
            'sqlite' => $this->connect(
                'sqlite:' . ($this->config['path'] ?? ':memory:')
            ),
            'mysql' => $this->connect(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $this->config['host'] ?? 'localhost',
                    $this->config['port'] ?? 3306,
                    $this->config['database'] ?? ''
                ),
                $this->config['user'] ?? null,
                $this->config['password'] ?? null
            ),
            'pgsql' => $this->connect(
                sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    $this->config['host'] ?? 'localhost',
                    $this->config['port'] ?? 5432,
                    $this->config['database'] ?? ''
                ),
                $this->config['user'] ?? null,
                $this->config['password'] ?? null
            ),
            default => throw new \RuntimeException('Unbekannter Treiber: ' . $driver),
        };
    }
    
    /**
     * Holt alle Tabellen der Datenbank
     */
    public function getTables(): array
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Nicht verbunden');
        }
        
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        $sql = match ($driver) {
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
            'mysql' => "SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name",
            'pgsql' => "SELECT tablename AS name FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename",
            default => throw new \RuntimeException('Treiber nicht unterstützt: ' . $driver),
        };
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
    
    /**
     * Holt Spalten-Info einer Tabelle
     */
    public function getColumns(string $table): array
    {
        if (!$this->pdo) {
            throw new \RuntimeException('Nicht verbunden');
        }
        
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        return match ($driver) {
            'sqlite' => $this->getColumnsSQLite($table),
            'mysql' => $this->getColumnsMySQL($table),
            'pgsql' => $this->getColumnsPgSQL($table),
            default => throw new \RuntimeException('Treiber nicht unterstützt: ' . $driver),
        };
    }
    
    private function getColumnsSQLite(string $table): array
    {
        $stmt = $this->pdo->query("PRAGMA table_info({$table})");
        $columns = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $columns[] = [
                'name' => $row['name'],
                'type' => $row['type'],
                'nullable' => !$row['notnull'],
                'default' => $row['dflt_value'],
                'primary' => (bool) $row['pk'],
            ];
        }
        return $columns;
    }
    
    private function getColumnsMySQL(string $table): array
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `{$table}`");
        $columns = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $columns[] = [
                'name' => $row['Field'],
                'type' => $row['Type'],
                'nullable' => $row['Null'] === 'YES',
                'default' => $row['Default'],
                'primary' => $row['Key'] === 'PRI',
            ];
        }
        return $columns;
    }
    
    private function getColumnsPgSQL(string $table): array
    {
        $sql = "SELECT column_name, data_type, is_nullable, column_default 
                FROM information_schema.columns 
                WHERE table_name = :table 
                ORDER BY ordinal_position";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':table' => $table]);
        
        $columns = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $columns[] = [
                'name' => $row['column_name'],
                'type' => $row['data_type'],
                'nullable' => $row['is_nullable'] === 'YES',
                'default' => $row['column_default'],
                'primary' => false,
            ];
        }
        return $columns;
    }
    
    /**
     * Zählt Zeilen einer Tabelle
     */
    public function countRows(string $table): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM {$table}");
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Holt Zeilen in Batches
     */
    public function fetchBatch(string $table, array $columns, int $offset, int $limit): array
    {
        $colList = implode(', ', array_map(fn($c) => '"' . $c . '"', $columns));
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        
        // SQLite LIMIT/OFFSET
        // MySQL LIMIT offset, count
        // PostgreSQL LIMIT count OFFSET offset
        $sql = match ($driver) {
            'sqlite', 'pgsql' => "SELECT {$colList} FROM {$table} LIMIT {$limit} OFFSET {$offset}",
            'mysql' => "SELECT {$colList} FROM {$table} LIMIT {$offset}, {$limit}",
            default => "SELECT {$colList} FROM {$table} LIMIT {$limit} OFFSET {$offset}",
        };
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Aktualisiert eine Zeile
     */
    public function updateRow(string $table, array $data, array $where): bool
    {
        $sets = [];
        $params = [];
        
        foreach ($data as $col => $val) {
            $sets[] = "\"{$col}\" = :set_{$col}";
            $params[":set_{$col}"] = $val;
        }
        
        $wheres = [];
        foreach ($where as $col => $val) {
            $wheres[] = "\"{$col}\" = :where_{$col}";
            $params[":where_{$col}"] = $val;
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE " . implode(' AND ', $wheres);
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Holt Primärschlüssel-Spalten
     */
    public function getPrimaryKeys(string $table): array
    {
        $columns = $this->getColumns($table);
        $pks = [];
        foreach ($columns as $col) {
            if ($col['primary']) {
                $pks[] = $col['name'];
            }
        }
        return $pks;
    }
    
    /**
     * Prüft ob verbunden
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }
    
    /**
     * Gibt PDO zurück (für direkte Operationen)
     */
    public function getPdo(): ?\PDO
    {
        return $this->pdo;
    }
    
    /**
     * Schließt Verbindung
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }
}
