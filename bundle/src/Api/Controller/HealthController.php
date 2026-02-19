<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\Api\Controller;

use LarsVanDerSangen\ProjectTemplateBundle\Shared\Service\HealthCheckService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService
    ) {
    }

    /**
     * Comprehensive health check endpoint.
     * Returns detailed status of all services and dependencies.
     */
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $healthStatus = $this->healthCheckService->checkAllServices();

        $statusCode = $healthStatus['status'] === 'healthy' ? 200 : 503;

        return $this->json($healthStatus, $statusCode);
    }

    /**
     * Kubernetes liveness probe endpoint.
     * Checks if the application is alive and should not be restarted.
     * Only fails on critical application errors, not external dependencies.
     *
     * Returns:
     * - HTTP 200: Application is alive
     * - HTTP 503: Application is dead/deadlocked (Kubernetes will restart the pod)
     */
    #[Route('/api/health/liveness', name: 'api_health_liveness', methods: ['GET'])]
    public function liveness(): JsonResponse
    {
        $livenessStatus = $this->healthCheckService->checkLiveness();

        // Liveness should always return 200 unless there's a critical application error
        return $this->json($livenessStatus, 200);
    }

    /**
     * Kubernetes readiness probe endpoint.
     * Checks if the application is ready to accept traffic.
     * Fails when critical external dependencies are unavailable.
     *
     * Returns:
     * - HTTP 200: Application is ready to accept traffic
     * - HTTP 503: Application is not ready (Kubernetes will not route traffic to this pod)
     */
    #[Route('/api/health/readiness', name: 'api_health_readiness', methods: ['GET'])]
    public function readiness(): JsonResponse
    {
        $readinessStatus = $this->healthCheckService->checkReadiness();

        $statusCode = $readinessStatus['status'] === 'ready' ? 200 : 503;

        return $this->json($readinessStatus, $statusCode);
    }
}

