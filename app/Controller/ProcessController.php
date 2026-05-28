<?php
/**
 * DataDachs – Process Controller
 * Führt Pseudonymisierung basierend auf bestätigten Regeln aus
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

class ProcessController
{
    private JobManager $jobManager;
    private PiiDetector $detector;
    private FakerEngine $faker;

    public function __construct(JobManager $jobManager, PiiDetector $detector, FakerEngine $faker)
    {
        $this->jobManager = $jobManager;
        $this->detector = $detector;
        $this->faker = $faker;
    }

    public function process(Request $request, Response $response, array $args): Response
    {
        $jobId = $args['jobId'];
        $body = $request->getParsedBody();
        $confirmedRules = $body['rules'] ?? [];

        $job = $this->jobManager->getJob($jobId);
        if (!$job) {
            return $this->jsonResponse($response, ['error' => 'Job nicht gefunden'], 404);
        }

        try {
            $content = file_get_contents($job['file_path']);
            $result = $this->pseudonymizeContent($content, $job['file_type'], $confirmedRules);

            if ($result !== null) {
                $resultPath = $this->jobManager->generateResultPath($job['original_name']);
                file_put_contents($resultPath, $result);
                $this->jobManager->setResult($jobId, $resultPath);
                $this->jobManager->setConfirmedRules($jobId, $confirmedRules);

                // Mapping löschen (Datenschutz)
                $this->faker->clearMapping();

                return $this->jsonResponse($response, [
                    'success' => true,
                    'download_url' => '/download/' . $jobId,
                ]);
            }
        } catch (\Exception $e) {
            $this->jobManager->setError($jobId, $e->getMessage());
            return $this->jsonResponse($response, ['error' => 'Verarbeitung fehlgeschlagen: ' . $e->getMessage()], 500);
        }

        return $this->jsonResponse($response, ['error' => 'Unbekannter Dateityp'], 400);
    }

    private function pseudonymizeContent(string $content, string $type, array $rules): ?string
    {
        return match ($type) {
            'sql' => (new SqlParser($this->detector, $this->faker))->pseudonymize($content, $rules),
            'csv' => (new CsvParser($this->detector, $this->faker))->pseudonymize($content, $rules),
            'json' => (new JsonParser($this->detector, $this->faker))->pseudonymize($content, $rules),
            'txt' => (new TxtParser($this->faker))->pseudonymize($content, $rules),
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
