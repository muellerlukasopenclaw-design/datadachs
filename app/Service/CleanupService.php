<?php
/**
 * DataDachs – Cleanup Service
 * Automatische Bereinigung abgelaufener Jobs
 */

namespace DataDachs\Service;

class CleanupService
{
    private JobManager $jobManager;
    private int $intervalMinutes;

    public function __construct(JobManager $jobManager, int $intervalMinutes = 5)
    {
        $this->jobManager = $jobManager;
        $this->intervalMinutes = $intervalMinutes;
    }

    /**
     * Führt Cleanup durch und gibt Anzahl gelöschter Jobs zurück
     */
    public function run(): int
    {
        return $this->jobManager->cleanupExpired();
    }

    /**
     * Prüft ob Cleanup fällig ist (basierend auf Intervall)
     */
    public function isDue(?int $lastRun = null): bool
    {
        if ($lastRun === null) {
            return true;
        }
        return (time() - $lastRun) > ($this->intervalMinutes * 60);
    }

    /**
     * Bereinigt auch verwaiste Dateien im Storage
     */
    public function cleanupOrphanedFiles(): int
    {
        $deleted = 0;
        $storageDir = dirname($this->jobManager->generateFilePath('tmp'));
        
        // Alle Dateien im Upload-Verzeichnis
        $uploadDir = $storageDir . '/uploads';
        if (is_dir($uploadDir)) {
            foreach (glob($uploadDir . '/*') as $file) {
                if (is_file($file) && filemtime($file) < time() - 3600) {
                    unlink($file);
                    $deleted++;
                }
            }
        }
        
        // Alle Dateien im Job-Verzeichnis
        $jobDir = $storageDir . '/jobs';
        if (is_dir($jobDir)) {
            foreach (glob($jobDir . '/*') as $file) {
                if (is_file($file) && filemtime($file) < time() - 3600) {
                    unlink($file);
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
}
