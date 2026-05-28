<?php
/**
 * DataDachs – Cron-Endpoint für Cleanup
 * Aufrufbar per HTTP-Request oder Cron-Job
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$config = require __DIR__ . '/../config/app.php';

$jobManager = new \DataDachs\Service\JobManager($config);
$cleanup = new \DataDachs\Service\CleanupService($jobManager);

$deletedJobs = $cleanup->run();
$deletedFiles = $cleanup->cleanupOrphanedFiles();

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'deleted_jobs' => $deletedJobs,
    'deleted_orphaned_files' => $deletedFiles,
    'timestamp' => date('c'),
]);
