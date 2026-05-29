<?php
/**
 * DataDachs – Home Controller
 * Rendert die Startseite mit Footer-Links
 */

namespace DataDachs\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomeController
{
    public function showHome(Request $request, Response $response): Response
    {
        $html = file_get_contents(__DIR__ . '/../../app/View/upload.html');
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

        return $html;
    }
}
