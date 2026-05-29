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

        return $html;
    }
}
