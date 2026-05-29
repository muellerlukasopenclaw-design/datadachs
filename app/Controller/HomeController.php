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

        $links = [];
        if ($impressum) {
            $links[] = '<a href="' . htmlspecialchars($impressum) . '" target="_blank">Impressum</a>';
        }
        if ($datenschutz) {
            $links[] = '<a href="' . htmlspecialchars($datenschutz) . '" target="_blank">Datenschutz</a>';
        }

        $footerExtra = '';
        if (!empty($links)) {
            $footerExtra = ' | ' . implode(' | ', $links);
        }

        $html = str_replace('{{FOOTER_LINKS}}', $footerExtra, $html);

        return $html;
    }
}
