<?php
/**
 * DataDachs – App-Konfiguration
 */

return [
    'name' => $_ENV['APP_NAME'] ?? 'DataDachs',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    
    'upload' => [
        'max_size_mb' => (int) ($_ENV['MAX_FILE_SIZE_MB'] ?? 50),
        'allowed_extensions' => ['sql', 'csv', 'json', 'txt', 'zip', 'gz'],
        'temp_dir' => __DIR__ . '/../storage/uploads',
    ],
    
    'job' => [
        'ttl_minutes' => (int) ($_ENV['JOB_TTL_MINUTES'] ?? 60),
        'storage_dir' => __DIR__ . '/../storage/jobs',
    ],
    
    'db' => [
        'path' => __DIR__ . '/../' . ($_ENV['DB_PATH'] ?? 'storage/datadachs.db'),
    ],
    
    'logging' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'warning',
        'path' => __DIR__ . '/../storage/logs',
    ],
];
