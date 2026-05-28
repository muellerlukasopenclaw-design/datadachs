<?php
/**
 * DataDachs – Download Controller
 * Liefert pseudonymisierte Dateien aus
 */

namespace DataDachs\Controller;

use DataDachs\Service\JobManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DownloadController
{
    private JobManager $jobManager;

    public function __construct(JobManager $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    public function download(Request $request, Response $response, array $args): Response
    {
        $jobId = $args['jobId'];
        $job = $this->jobManager->getJob($jobId);

        if (!$job || empty($job['result_path']) || !file_exists($job['result_path'])) {
            $response->getBody()->write('Datei nicht gefunden');
            return $response->withStatus(404);
        }

        $file = $job['result_path'];
        $filename = basename($job['original_name']);
        $filename = preg_replace('/\.([^.]+)$/', '_pseudonymized.$1', $filename);

        $response = $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) filesize($file));

        $response->getBody()->write(file_get_contents($file));
        return $response;
    }
}
