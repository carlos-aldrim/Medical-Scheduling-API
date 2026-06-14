<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/health', name: 'health_check', methods: ['GET'])]
#[OA\Tag(name: 'Health')]
class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    #[OA\Get(
        summary: 'Health check',
        description: 'Checks database connectivity and opcache status. Returns 200 if healthy, 503 otherwise.',
        responses: [
            new OA\Response(response: 200, description: 'Service is healthy'),
            new OA\Response(response: 503, description: 'Service is unhealthy'),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $allOk  = true;

        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['database'] = [
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
            $allOk = false;
        }

        if (function_exists('opcache_get_status')) {
            $opcache = @opcache_get_status(false);
            $checks['opcache'] = [
                'status'  => ($opcache !== false) ? 'ok' : 'disabled',
                'enabled' => $opcache !== false,
            ];
        }

        $httpStatus = $allOk ? 200 : 503;

        return new JsonResponse([
            'status' => $allOk ? 'ok' : 'error',
            'checks' => $checks,
        ], $httpStatus);
    }
}
