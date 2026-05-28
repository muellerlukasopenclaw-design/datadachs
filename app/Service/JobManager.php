<?php
/**
 * DataDachs – Job Manager
 * Verwaltet Uploads, Verarbeitung und Bereinigung
 */

namespace DataDachs\Service;

use PDO;
use PDOException;

class JobManager
{
    private PDO $db;
    private string $uploadDir;
    private string $jobDir;
    private int $ttlMinutes;
    
    public function __construct(array $config)
    {
        $this->uploadDir = $config['upload']['temp_dir'] ?? __DIR__ . '/../../storage/uploads';
        $this->jobDir = $config['job']['storage_dir'] ?? __DIR__ . '/../../storage/jobs';
        $this->ttlMinutes = $config['job']['ttl_minutes'] ?? 60;
        
        // Verzeichnisse sicherstellen
        foreach ([$this->uploadDir, $this->jobDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
        }
        
        // SQLite-Verbindung
        $dbPath = $config['db']['path'] ?? __DIR__ . '/../../storage/datadachs.db';
        $this->db = new PDO("sqlite:$dbPath");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initSchema();
    }
    
    private function initSchema(): void
    {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS jobs (
                id TEXT PRIMARY KEY,
                original_name TEXT NOT NULL,
                file_path TEXT NOT NULL,
                file_type TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT "pending",
                detected_rules TEXT,
                confirmed_rules TEXT,
                result_path TEXT,
                created_at INTEGER NOT NULL,
                expires_at INTEGER NOT NULL,
                error_message TEXT
            )
        ');
    }
    
    /**
     * Erstellt einen neuen Job
     */
    public function createJob(string $originalName, string $filePath, string $fileType): string
    {
        $jobId = bin2hex(random_bytes(16));
        $now = time();
        $expires = $now + ($this->ttlMinutes * 60);
        
        $stmt = $this->db->prepare('
            INSERT INTO jobs (id, original_name, file_path, file_type, status, created_at, expires_at)
            VALUES (:id, :name, :path, :type, :status, :created, :expires)
        ');
        
        $stmt->execute([
            ':id' => $jobId,
            ':name' => $originalName,
            ':path' => $filePath,
            ':type' => $fileType,
            ':status' => 'pending',
            ':created' => $now,
            ':expires' => $expires,
        ]);
        
        return $jobId;
    }
    
    /**
     * Speichert erkannte Regeln
     */
    public function setDetectedRules(string $jobId, array $rules): void
    {
        $stmt = $this->db->prepare('
            UPDATE jobs SET detected_rules = :rules, status = :status WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $jobId,
            ':rules' => json_encode($rules),
            ':status' => 'analyzed',
        ]);
    }
    
    /**
     * Speichert bestätigte Regeln
     */
    public function setConfirmedRules(string $jobId, array $rules): void
    {
        $stmt = $this->db->prepare('
            UPDATE jobs SET confirmed_rules = :rules WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $jobId,
            ':rules' => json_encode($rules),
        ]);
    }
    
    /**
     * Speichert Ergebnis
     */
    public function setResult(string $jobId, string $resultPath): void
    {
        $stmt = $this->db->prepare('
            UPDATE jobs SET result_path = :path, status = :status WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $jobId,
            ':path' => $resultPath,
            ':status' => 'completed',
        ]);
    }
    
    /**
     * Markiert Job als fehlgeschlagen
     */
    public function setError(string $jobId, string $message): void
    {
        $stmt = $this->db->prepare('
            UPDATE jobs SET status = :status, error_message = :error WHERE id = :id
        ');
        $stmt->execute([
            ':id' => $jobId,
            ':status' => 'failed',
            ':error' => $message,
        ]);
    }
    
    /**
     * Holt Job-Informationen
     */
    public function getJob(string $jobId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            return null;
        }
        
        // JSON dekodieren
        $job['detected_rules'] = json_decode($job['detected_rules'] ?? 'null', true);
        $job['confirmed_rules'] = json_decode($job['confirmed_rules'] ?? 'null', true);
        
        return $job;
    }
    
    /**
     * Löscht abgelaufene Jobs und Dateien
     */
    public function cleanupExpired(): int
    {
        $now = time();
        
        $stmt = $this->db->prepare('SELECT * FROM jobs WHERE expires_at < :now');
        $stmt->execute([':now' => $now]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $deleted = 0;
        foreach ($jobs as $job) {
            // Dateien löschen
            if (!empty($job['file_path']) && file_exists($job['file_path'])) {
                unlink($job['file_path']);
            }
            if (!empty($job['result_path']) && file_exists($job['result_path'])) {
                unlink($job['result_path']);
            }
            
            // DB-Eintrag löschen
            $del = $this->db->prepare('DELETE FROM jobs WHERE id = :id');
            $del->execute([':id' => $job['id']]);
            $deleted++;
        }
        
        return $deleted;
    }
    
    /**
     * Löscht einen einzelnen Job manuell
     */
    public function deleteJob(string $jobId): bool
    {
        $job = $this->getJob($jobId);
        if (!$job) {
            return false;
        }
        
        if (!empty($job['file_path']) && file_exists($job['file_path'])) {
            unlink($job['file_path']);
        }
        if (!empty($job['result_path']) && file_exists($job['result_path'])) {
            unlink($job['result_path']);
        }
        
        $stmt = $this->db->prepare('DELETE FROM jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        
        return true;
    }
    
    /**
     * Generiert sicheren Dateipfad
     */
    public function generateFilePath(string $extension): string
    {
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        return $this->uploadDir . '/' . $filename;
    }
    
    /**
     * Generiert Ergebnis-Dateipfad
     */
    public function generateResultPath(string $originalName): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = $base . '_pseudonymized_' . bin2hex(random_bytes(4)) . '.' . $ext;
        return $this->jobDir . '/' . $filename;
    }
}
