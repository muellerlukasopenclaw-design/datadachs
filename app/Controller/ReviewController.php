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
        $html = $this->injectVersion($html);
        $html = $this->injectFooter($html);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    private function injectVersion(string $html): string
    {
        $versionFile = __DIR__ . '/../../config/version.php';
        $version = '1.0.0';
        if (file_exists($versionFile)) {
            $versionData = include $versionFile;
            if (is_array($versionData) && isset($versionData['version'])) {
                $version = $versionData['version'];
            }
        }
        $html = str_replace('{{VERSION}}', htmlspecialchars($version), $html);
        return $html;
    }

    private function injectFooter(string $html): string
    {
        $impressum = getenv('DATADACHS_IMPRESSUM_URL') ?: '';
        $datenschutz = getenv('DATADACHS_DATENSCHUTZ_URL') ?: '';

        // Feste Links – Autor/Inhaber bleibt unabhängig vom Host
        $github = 'https://github.com/muellerlukasopenclaw-design/datadachs';
        $donate = 'https://www.paypal.com/paypalme/Lukas1809';

        $links = [];
        if ($impressum) {
            $links[] = '<a href="' . htmlspecialchars($impressum) . '" target="_blank">Impressum</a>';
        }
        if ($datenschutz) {
            $links[] = '<a href="' . htmlspecialchars($datenschutz) . '" target="_blank">Datenschutz</a>';
        }
        $links[] = '<a href="' . htmlspecialchars($github) . '" target="_blank">GitHub</a>';
        $links[] = '<a href="' . htmlspecialchars($donate) . '" target="_blank">☕ Kaffee spendieren</a>';

        $footerExtra = ' | ' . implode(' | ', $links);

        $html = str_replace('{{FOOTER_LINKS}}', $footerExtra, $html);

        // Fallback für alte Templates ohne Platzhalter – behalte aktuelle Version bei
        $html = preg_replace('/DataDachs v([\d.]+) – Lokale Pseudonymisierung ohne Cloud/', 'DataDachs v$1 – Lokale Pseudonymisierung ohne Cloud' . $footerExtra, $html);

        return $html;
    }
}
