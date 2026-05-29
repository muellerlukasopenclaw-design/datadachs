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

    public function showReview(Request $request, Response $response, array $args): Response
    {
        $jobId = $args['jobId'];
        $job = $this->jobManager->getJob($jobId);

        if (!$job) {
            $response->getBody()->write('Job nicht gefunden');
            return $response->withStatus(404);
        }

        $html = file_get_contents(__DIR__ . '/../../app/View/review.html');
        $html = str_replace('{{JOB_ID}}', $jobId, $html);
        $html = str_replace('{{JOB_DATA}}', json_encode($job), $html);
        $html = $this->injectFooter($html);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    private function injectFooter(string $html): string
    {
        $impressum = getenv('DATADACHS_IMPRESSUM_URL') ?: '';
        $datenschutz = getenv('DATADACHS_DATENSCHUTZ_URL') ?: '';
        $github = getenv('DATADACHS_GITHUB_URL') ?: 'https://github.com/muellerlukasopenclaw-design/datadachs';
        $donate = getenv('DATADACHS_DONATE_URL') ?: '';

        $links = [];
        if ($impressum) {
            $links[] = '<a href="' . htmlspecialchars($impressum) . '" target="_blank">Impressum</a>';
        }
        if ($datenschutz) {
            $links[] = '<a href="' . htmlspecialchars($datenschutz) . '" target="_blank">Datenschutz</a>';
        }
        if ($github) {
            $links[] = '<a href="' . htmlspecialchars($github) . '" target="_blank">GitHub</a>';
        }
        if ($donate) {
            $links[] = '<a href="' . htmlspecialchars($donate) . '" target="_blank">☕ Kaffee spendieren</a>';
        }

        $footerExtra = '';
        if (!empty($links)) {
            $footerExtra = ' | ' . implode(' | ', $links);
        }

        $html = str_replace('{{FOOTER_LINKS}}', $footerExtra, $html);

        // Fallback für alte Templates ohne Platzhalter
        $html = preg_replace('/DataDachs v[\d.]+ – Lokale Pseudonymisierung ohne Cloud/', 'DataDachs v1.0.8 – Lokale Pseudonymisierung ohne Cloud' . $footerExtra, $html);

        return $html;
    }
}
