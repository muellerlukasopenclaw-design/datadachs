<?php
/**
 * DataDachs – Upload Controller
 * Verarbeitet Datei-Uploads und leitet zur Analyse weiter
 */

namespace DataDachs\Controller;

use DataDachs\Service\JobManager;
use DataDachs\Service\PiiDetector;
use DataDachs\Service\FakerEngine;
use DataDachs\Parser\SqlParser;
use DataDachs\Parser\CsvParser;
use DataDachs\Parser\JsonParser;
use DataDachs\Parser\TxtParser;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UploadController
{
    private JobManager $jobManager;
    private PiiDetector $detector;
    private FakerEngine $faker;
    private array $config;

    public function __construct(JobManager $jobManager, PiiDetector $detector, FakerEngine $faker, array $config)
    {
        $this->jobManager = $jobManager;
        $this->detector = $detector;
        $this->faker = $faker;
        $this->config = $config;
    }

    public function handleUpload(Request $request, Response $response): Response
    {
        $uploadedFiles = $request->getUploadedFiles();
        $uploadedFile = $uploadedFiles['file'] ?? null;

        if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return $this->jsonResponse($response, ['error' => 'Upload fehlgeschlagen'], 400);
        }

        $originalName = $uploadedFile->getClientFilename();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $allowed = $this->config['upload']['allowed_extensions'] ?? ['sql', 'csv', 'json', 'txt'];
        if (!in_array($extension, $allowed)) {
            return $this->jsonResponse($response, ['error' => 'Dateityp nicht unterstützt'], 400);
        }

        $filePath = $this->jobManager->generateFilePath($extension);
        $uploadedFile->moveTo($filePath);

        $jobId = $this->jobManager->createJob($originalName, $filePath, $extension);

        // Analyse
        try {
            $content = file_get_contents($filePath);
            $analysis = $this->analyzeContent($content, $extension);

            if ($analysis) {
                $this->jobManager->setDetectedRules($jobId, $analysis);
            }
        } catch (\Exception $e) {
            $this->jobManager->setError($jobId, $e->getMessage());
            return $this->jsonResponse($response, ['error' => 'Analyse fehlgeschlagen: ' . $e->getMessage()], 500);
        }

        return $this->jsonResponse($response, [
            'job_id' => $jobId,
            'redirect' => '/review/' . $jobId,
        ]);
    }

    private function analyzeContent(string $content, string $extension): ?array
    {
        return match ($extension) {
            'sql' => (new SqlParser($this->detector, $this->faker))->analyze($content),
            'csv' => (new CsvParser($this->detector, $this->faker))->analyze($content),
            'json' => (new JsonParser($this->detector, $this->faker))->analyze($content),
            'txt' => (new TxtParser($this->faker))->analyze($content),
            default => null,
        };
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
