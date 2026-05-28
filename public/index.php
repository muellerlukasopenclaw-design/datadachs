<?php
/**
 * DataDachs – Front Controller
 * Slim-Routing mit DI-Container
 */

use Slim\Factory\AppFactory;
use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

// .env laden
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Konfiguration
$config = require __DIR__ . '/../config/app.php';

// Container mit DI
$builder = new ContainerBuilder();
$builder->addDefinitions([
    'config' => $config,
    \DataDachs\Service\JobManager::class => \DI\create(\DataDachs\Service\JobManager::class)->constructor($config),
    \DataDachs\Service\PiiDetector::class => \DI\create(\DataDachs\Service\PiiDetector::class),
    \DataDachs\Service\FakerEngine::class => function () {
        return new \DataDachs\Service\FakerEngine($_ENV['DETERMINISTIC_SEED'] ?? null);
    },
    \DataDachs\Service\PreserveRuleService::class => function () {
        $preserveConfig = $config['preserve'] ?? [];
        $rules = $preserveConfig['rules'] ?? null;
        $caseSensitive = $preserveConfig['case_sensitive'] ?? false;
        $useDefaults = $preserveConfig['use_defaults'] ?? true;
        
        if ($useDefaults && empty($rules)) {
            return \DataDachs\Service\PreserveRuleService::withDefaults();
        }
        
        $service = new \DataDachs\Service\PreserveRuleService($rules, $caseSensitive);
        
        if ($useDefaults) {
            foreach (\DataDachs\Service\PreserveRuleService::getDefaultRules() as $rule) {
                $service->addRule($rule);
            }
        }
        
        return $service;
    },
    // Controller
    \DataDachs\Controller\UploadController::class => \DI\create(\DataDachs\Controller\UploadController::class)
        ->constructor(\DI\get(\DataDachs\Service\JobManager::class), \DI\get(\DataDachs\Service\PiiDetector::class), \DI\get(\DataDachs\Service\FakerEngine::class), \DI\get('config'), \DI\get(\DataDachs\Service\PreserveRuleService::class)),
    \DataDachs\Controller\ReviewController::class => \DI\create(\DataDachs\Controller\ReviewController::class)
        ->constructor(\DI\get(\DataDachs\Service\JobManager::class)),
    \DataDachs\Controller\ProcessController::class => \DI\create(\DataDachs\Controller\ProcessController::class)
        ->constructor(\DI\get(\DataDachs\Service\JobManager::class), \DI\get(\DataDachs\Service\PiiDetector::class), \DI\get(\DataDachs\Service\FakerEngine::class), \DI\get(\DataDachs\Service\PreserveRuleService::class)),
    \DataDachs\Controller\DownloadController::class => \DI\create(\DataDachs\Controller\DownloadController::class)
        ->constructor(\DI\get(\DataDachs\Service\JobManager::class)),
    \DataDachs\Controller\CleanupController::class => \DI\create(\DataDachs\Controller\CleanupController::class)
        ->constructor(\DI\get(\DataDachs\Service\JobManager::class)),
    \DataDachs\Controller\HealthController::class => \DI\create(\DataDachs\Controller\HealthController::class),
    \DataDachs\Controller\DbController::class => \DI\create(\DataDachs\Controller\DbController::class)
        ->constructor(\DI\get('config'), \DI\get(\DataDachs\Service\PreserveRuleService::class)),
]);

$container = $builder->build();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Error Middleware
$app->addErrorMiddleware(true, true, true);

// Body Parsing
$app->addBodyParsingMiddleware();

// === ROUTES ===

// Health Check
$app->get('/health', \DataDachs\Controller\HealthController::class . ':check');

// Upload-Seite
$app->get('/', function ($request, $response) {
    $html = file_get_contents(__DIR__ . '/../app/View/upload.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Upload-Handler
$app->post('/upload', \DataDachs\Controller\UploadController::class . ':handleUpload');

// Review-Seite
$app->get('/review/{jobId}', \DataDachs\Controller\ReviewController::class . ':showReview');

// Pseudonymisierung
$app->post('/process/{jobId}', \DataDachs\Controller\ProcessController::class . ':process');

// Download
$app->get('/download/{jobId}', \DataDachs\Controller\DownloadController::class . ':download');

// Cleanup
$app->post('/cleanup', \DataDachs\Controller\CleanupController::class . ':cleanup');

// Hilfe
$app->get('/help', function ($request, $response) {
    $html = file_get_contents(__DIR__ . '/../app/View/help.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// === DATENBANK-MODUS ===
$app->post('/db/connect', \DataDachs\Controller\DbController::class . ':connect');
$app->post('/db/analyze', \DataDachs\Controller\DbController::class . ':analyze');
$app->post('/db/pseudonymize', \DataDachs\Controller\DbController::class . ':pseudonymize');
$app->post('/db/export', \DataDachs\Controller\DbController::class . ':export');
$app->post('/db/disconnect', \DataDachs\Controller\DbController::class . ':disconnect');

// Datenbank-UI
$app->get('/db', function ($request, $response) {
    $html = file_get_contents(__DIR__ . '/../app/View/db.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

$app->run();
