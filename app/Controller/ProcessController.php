<?php
/**
 * DataDachs – Process Controller
 * Führt Pseudonymisierung basierend auf bestätigten Regeln aus
 */

namespace DataDachs\Controller;

use DataDachs\Service\JobManager;
use DataDachs\Service\PiiDetector;
use DataDachs\Service\FakerEngine;
use DataDachs\Service\PreserveRuleService;
use DataDachs\Parser\SqlParser;
use DataDachs\Parser\CsvParser;
use DataDachs\Parser\JsonParser;
use DataDachs\Parser\TxtParser;
use DataDachs\Parser\DocxParser;
use DataDachs\Parser\XlsxParser;
use DataDachs\Parser\PdfParser;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProcessController
{
    private JobManager $jobManager;
    private PiiDetector $detector;
    private FakerEngine $faker;
    private ?PreserveRuleService $preserveService;

    public function __construct(JobManager $jobManager, PiiDetector $detector, FakerEngine $faker, ?PreserveRuleService $preserveService = null)
    {
        $this->jobManager = $jobManager;
        $this->detector = $detector;
        $this->faker = $faker;
        $this->preserveService = $preserveService;
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

        // Konvertiere einfaches Array ['phone_de', 'email'] zu assoziativem Array ['phone_de' => true, 'email' => true]
        $enabledTypes = [];
        foreach ($confirmedRules as $rule) {
            $enabledTypes[$rule] = true;
        }

        try {
            $result = $this->pseudonymizeFile($job['file_path'], $job['file_type'], $enabledTypes);

            if ($result !== null) {
                $resultPath = $this->jobManager->generateResultPath($job['original_name']);
                
                // Bei DOCX/XLSX/PDF ist $result ein Pfad zur temporären Datei
                if (in_array($job['file_type'], ['docx', 'xlsx', 'pdf'])) {
                    rename($result, $resultPath);
                } else {
                    file_put_contents($resultPath, $result);
                }
                
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

    private function pseudonymizeFile(string $filePath, string $type, array $rules): ?string
    {
        return match ($type) {
            'sql' => (new SqlParser($this->detector, $this->faker, $this->preserveService))->pseudonymize(file_get_contents($filePath), $rules),
            'csv' => (new CsvParser($this->detector, $this->faker, $this->preserveService))->pseudonymize(file_get_contents($filePath), $rules),
            'json' => (new JsonParser($this->detector, $this->faker, $this->preserveService))->pseudonymize(file_get_contents($filePath), $rules),
            'txt' => (new TxtParser($this->faker, $this->preserveService))->pseudonymize(file_get_contents($filePath), $rules),
            'docx' => (new DocxParser($this->detector, $this->faker, $this->preserveService))->pseudonymize($filePath, $rules),
            'xlsx' => (new XlsxParser($this->detector, $this->faker, $this->preserveService))->pseudonymize($filePath, $rules),
            'pdf' => (new PdfParser($this->detector, $this->faker, $this->preserveService))->pseudonymize($filePath, $rules),
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
