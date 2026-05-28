<?php
/**
 * DataDachs – Health Controller
 * Einfacher Health-Check für Monitoring
 */

namespace DataDachs\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthController
{
    public function check(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'app' => 'DataDachs',
            'version' => '1.0.0',
            'timestamp' => time(),
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json');
    }
}
