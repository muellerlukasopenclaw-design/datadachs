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
    // Controller
    \DataDachs\Controller\UploadController::class => \DI\autowire(),
    \DataDachs\Controller\ReviewController::class => \DI\autowire(),
    \DataDachs\Controller\ProcessController::class => \DI\autowire(),
    \DataDachs\Controller\DownloadController::class => \DI\autowire(),
    \DataDachs\Controller\CleanupController::class => \DI\autowire(),
    \DataDachs\Controller\HealthController::class => \DI\autowire(),
]);

$container = $builder->build();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Error Middleware
$app->addErrorMiddleware($config['debug'], false, false);

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

$app->run();
