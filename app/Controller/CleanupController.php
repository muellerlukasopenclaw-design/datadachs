<?php
/**
 * DataDachs – Cleanup Controller
 * Manuelles oder automatisches Cleanup
 */

namespace DataDachs\Controller;

use DataDachs\Service\JobManager;
use DataDachs\Service\CleanupService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CleanupController
{
    private JobManager $jobManager;

    public function __construct(JobManager $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    public function cleanup(Request $request, Response $response): Response
    {
        $cleanup = new CleanupService($this->jobManager);
        $deletedJobs = $cleanup->run();
        $deletedFiles = $cleanup->cleanupOrphanedFiles();

        $response->getBody()->write(json_encode([
            'deleted_jobs' => $deletedJobs,
            'deleted_orphaned_files' => $deletedFiles,
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json');
    }
}
