<?php
/**
 * DataDachs – Review Controller
 * Zeigt erkannte Regeln zur Bestätigung an
 */

namespace DataDachs\Controller;

use DataDachs\Service\JobManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReviewController
{
    private JobManager $jobManager;

    public function __construct(JobManager $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    public function showReview(Request $request, Response $response, string $jobId): Response
    {
        $job = $this->jobManager->getJob($jobId);

        if (!$job) {
            $response->getBody()->write('Job nicht gefunden');
            return $response->withStatus(404);
        }

        $html = file_get_contents(__DIR__ . '/../../app/View/review.html');
        $html = str_replace('{{JOB_ID}}', $jobId, $html);
        $html = str_replace('{{JOB_DATA}}', json_encode($job), $html);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }
}
