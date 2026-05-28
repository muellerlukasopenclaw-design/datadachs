<?php
/**
 * DataDachs – Database Controller
 * API für Datenbank-Modus: Connect, Analyze, Pseudonymize, Export
 */

namespace DataDachs\Controller;

use DataDachs\Service\DatabaseService;
use DataDachs\Service\DbPseudonymizer;
use DataDachs\Service\PiiDetector;
use DataDachs\Service\FakerEngine;
use DataDachs\Service\PreserveRuleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DbController
{
    private array $config;
    private ?PreserveRuleService $preserveService;
    
    public function __construct(array $config, ?PreserveRuleService $preserveService = null)
    {
        $this->config = $config;
        $this->preserveService = $preserveService;
    }
    
    /**
     * POST /db/connect – Verbindung herstellen
     */
    public function connect(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        
        $driver = $body['driver'] ?? 'sqlite';
        
        try {
            $dbService = new DatabaseService();
            
            switch ($driver) {
                case 'sqlite':
                    $path = $body['path'] ?? ($this->config['db']['path'] ?? 'storage/datadachs.db');
                    $dbService->connect("sqlite:{$path}");
                    break;
                    
                case 'mysql':
                    $dbService->connect(
                        sprintf(
                            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                            $body['host'] ?? 'localhost',
                            $body['port'] ?? 3306,
                            $body['database'] ?? ''
                        ),
                        $body['user'] ?? null,
                        $body['password'] ?? null
                    );
                    break;
                    
                case 'pgsql':
                    $dbService->connect(
                        sprintf(
                            'pgsql:host=%s;port=%s;dbname=%s',
                            $body['host'] ?? 'localhost',
                            $body['port'] ?? 5432,
                            $body['database'] ?? ''
                        ),
                        $body['user'] ?? null,
                        $body['password'] ?? null
                    );
                    break;
                    
                default:
                    return $this->jsonResponse($response, ['error' => 'Unbekannter Treiber: ' . $driver], 400);
            }
            
            // Tabellen holen
            $tables = $dbService->getTables();
            
            // Session-Token für DB-Verbindung (einfach: in Datei speichern)
            $sessionId = $this->createDbSession($body);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'session_id' => $sessionId,
                'driver' => $driver,
                'tables' => $tables,
                'table_count' => count($tables),
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * POST /db/analyze – Schema analysieren und PII erkennen
     */
    public function analyze(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $sessionId = $body['session_id'] ?? null;
        
        if (!$sessionId || !$this->validateDbSession($sessionId)) {
            return $this->jsonResponse($response, ['error' => 'Ungültige Session'], 401);
        }
        
        try {
            $dbService = $this->getDbServiceFromSession($sessionId);
            $detector = new PiiDetector();
            $faker = new FakerEngine($_ENV['DETERMINISTIC_SEED'] ?? null, $this->preserveService);
            
            $pseudonymizer = new DbPseudonymizer(
                $dbService,
                $detector,
                $faker,
                $this->preserveService
            );
            
            $analysis = $pseudonymizer->analyzeSchema();
            
            return $this->jsonResponse($response, [
                'success' => true,
                'tables_with_pii' => count($analysis),
                'analysis' => $analysis,
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * POST /db/pseudonymize – Pseudonymisierung ausführen
     */
    public function pseudonymize(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $sessionId = $body['session_id'] ?? null;
        $rules = $body['rules'] ?? []; // [table => [column => rule]]
        
        if (!$sessionId || !$this->validateDbSession($sessionId)) {
            return $this->jsonResponse($response, ['error' => 'Ungültige Session'], 401);
        }
        
        try {
            $dbService = $this->getDbServiceFromSession($sessionId);
            $detector = new PiiDetector();
            $faker = new FakerEngine($_ENV['DETERMINISTIC_SEED'] ?? null, $this->preserveService);
            
            $pseudonymizer = new DbPseudonymizer(
                $dbService,
                $detector,
                $faker,
                $this->preserveService,
                $body['batch_size'] ?? 1000
            );
            
            $results = $pseudonymizer->pseudonymizeTables($rules);
            
            // Ergebnis-Zusammenfassung
            $summary = [
                'total_processed' => 0,
                'total_updated' => 0,
                'total_errors' => 0,
                'tables' => [],
            ];
            
            foreach ($results as $table => $result) {
                $summary['total_processed'] += $result['processed'];
                $summary['total_updated'] += $result['updated'];
                $summary['total_errors'] += count($result['errors']);
                $summary['tables'][$table] = [
                    'processed' => $result['processed'],
                    'updated' => $result['updated'],
                    'errors' => count($result['errors']),
                ];
            }
            
            return $this->jsonResponse($response, [
                'success' => true,
                'summary' => $summary,
                'details' => $results,
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * POST /db/export – Export als SQL-Dump
     */
    public function export(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $sessionId = $body['session_id'] ?? null;
        $tables = $body['tables'] ?? [];
        
        if (!$sessionId || !$this->validateDbSession($sessionId)) {
            return $this->jsonResponse($response, ['error' => 'Ungültige Session'], 401);
        }
        
        try {
            $dbService = $this->getDbServiceFromSession($sessionId);
            $detector = new PiiDetector();
            $faker = new FakerEngine($_ENV['DETERMINISTIC_SEED'] ?? null, $this->preserveService);
            
            $pseudonymizer = new DbPseudonymizer($dbService, $detector, $faker, $this->preserveService);
            
            $output = [];
            $output[] = "-- DataDachs Database Export";
            $output[] = "-- Generiert: " . date('Y-m-d H:i:s');
            $output[] = "";
            
            foreach ($tables as $table => $columnRules) {
                $output[] = "-- Tabelle: {$table}";
                $output[] = $pseudonymizer->exportAsSql($table, $columnRules);
                $output[] = "";
            }
            
            $sql = implode("\n", $output);
            
            // Als Datei speichern
            $filename = 'datadachs_export_' . date('Y-m-d_His') . '.sql';
            $filepath = $this->config['job']['storage_dir'] ?? 'storage/jobs';
            $fullPath = $filepath . '/' . $filename;
            
            if (!is_dir($filepath)) {
                mkdir($filepath, 0755, true);
            }
            
            file_put_contents($fullPath, $sql);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'download_url' => '/download/' . basename($fullPath),
                'filename' => $filename,
                'size' => strlen($sql),
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * POST /db/disconnect – Verbindung trennen
     */
    public function disconnect(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $sessionId = $body['session_id'] ?? null;
        
        if ($sessionId) {
            $this->deleteDbSession($sessionId);
        }
        
        return $this->jsonResponse($response, ['success' => true]);
    }
    
    // === Session Management (einfach, Datei-basiert) ===
    
    private function createDbSession(array $config): string
    {
        $sessionId = 'db_' . bin2hex(random_bytes(16));
        $sessionDir = $this->config['job']['storage_dir'] ?? 'storage/jobs';
        
        if (!is_dir($sessionDir)) {
            mkdir($sessionDir, 0755, true);
        }
        
        $sessionFile = $sessionDir . '/db_sessions.json';
        $sessions = [];
        
        if (file_exists($sessionFile)) {
            $sessions = json_decode(file_get_contents($sessionFile), true) ?? [];
        }
        
        // Passwort aus Config entfernen (Sicherheit)
        $safeConfig = $config;
        unset($safeConfig['password']);
        
        $sessions[$sessionId] = [
            'config' => $safeConfig,
            'created' => time(),
            'expires' => time() + 3600, // 1 Stunde
        ];
        
        file_put_contents($sessionFile, json_encode($sessions, JSON_PRETTY_PRINT));
        
        return $sessionId;
    }
    
    private function validateDbSession(string $sessionId): bool
    {
        $sessionFile = ($this->config['job']['storage_dir'] ?? 'storage/jobs') . '/db_sessions.json';
        
        if (!file_exists($sessionFile)) {
            return false;
        }
        
        $sessions = json_decode(file_get_contents($sessionFile), true) ?? [];
        
        if (!isset($sessions[$sessionId])) {
            return false;
        }
        
        // Expired?
        if ($sessions[$sessionId]['expires'] < time()) {
            unset($sessions[$sessionId]);
            file_put_contents($sessionFile, json_encode($sessions, JSON_PRETTY_PRINT));
            return false;
        }
        
        return true;
    }
    
    private function getDbServiceFromSession(string $sessionId): DatabaseService
    {
        $sessionFile = ($this->config['job']['storage_dir'] ?? 'storage/jobs') . '/db_sessions.json';
        $sessions = json_decode(file_get_contents($sessionFile), true) ?? [];
        $config = $sessions[$sessionId]['config'] ?? [];
        
        $dbService = new DatabaseService();
        
        $driver = $config['driver'] ?? 'sqlite';
        
        switch ($driver) {
            case 'sqlite':
                $path = $config['path'] ?? 'storage/datadachs.db';
                $dbService->connect("sqlite:{$path}");
                break;
            case 'mysql':
                $dbService->connect(
                    sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                        $config['host'] ?? 'localhost',
                        $config['port'] ?? 3306,
                        $config['database'] ?? ''
                    ),
                    $config['user'] ?? null,
                    $config['password'] ?? null
                );
                break;
            case 'pgsql':
                $dbService->connect(
                    sprintf(
                        'pgsql:host=%s;port=%s;dbname=%s',
                        $config['host'] ?? 'localhost',
                        $config['port'] ?? 5432,
                        $config['database'] ?? ''
                    ),
                    $config['user'] ?? null,
                    $config['password'] ?? null
                );
                break;
        }
        
        return $dbService;
    }
    
    private function deleteDbSession(string $sessionId): void
    {
        $sessionFile = ($this->config['job']['storage_dir'] ?? 'storage/jobs') . '/db_sessions.json';
        
        if (!file_exists($sessionFile)) {
            return;
        }
        
        $sessions = json_decode(file_get_contents($sessionFile), true) ?? [];
        unset($sessions[$sessionId]);
        file_put_contents($sessionFile, json_encode($sessions, JSON_PRETTY_PRINT));
    }
    
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
