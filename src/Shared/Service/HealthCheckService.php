<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shared\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Mailer\MailerInterface;

class HealthCheckService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MailerInterface $mailer
    ) {
    }

    /**
     * Comprehensive health check - checks all services and dependencies.
     * Used for the general /api/health endpoint.
     */
    public function checkAllServices(): array
    {
        $services = [
            'database' => $this->checkDatabase(),
            'mailer' => $this->checkMailer(),
        ];

        $overallStatus = $this->determineOverallStatus($services);

        return [
            'status' => $overallStatus,
            'timestamp' => date('c'),
            'services' => $services,
        ];
    }

    /**
     * Liveness check - verifies the application is alive and not deadlocked.
     * Should only fail on critical application errors, not external dependencies.
     * Used for Kubernetes liveness probe.
     */
    public function checkLiveness(): array
    {
        // Liveness check is simple - if we can execute PHP code and return a response,
        // the application is alive. We don't check external dependencies here.
        return [
            'status' => 'alive',
            'timestamp' => date('c'),
        ];
    }

    /**
     * Readiness check - verifies the application is ready to accept traffic.
     * Checks all critical external dependencies (database, cache, etc.).
     * Used for Kubernetes readiness probe.
     */
    public function checkReadiness(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
        ];

        $isReady = array_all($checks, fn($check) => $check['status'] === 'healthy');

        return [
            'status' => $isReady ? 'ready' : 'not_ready',
            'timestamp' => date('c'),
            'checks' => $checks,
        ];
    }

    private function checkDatabase(): array
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            return [
                'status' => 'healthy',
                'message' => 'Database connection is working',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkMailer(): array
    {
        try {
            // Mailer is configured if the service is available
            // We don't actually send an email, just check if the service is configured
            if ($this->mailer !== null) {
                return [
                    'status' => 'healthy',
                    'message' => 'Mailer service is configured',
                ];
            }
            return [
                'status' => 'unhealthy',
                'message' => 'Mailer service is not configured',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Mailer service check failed: ' . $e->getMessage(),
            ];
        }
    }

    private function determineOverallStatus(array $services): string
    {
        foreach ($services as $service) {
            if ($service['status'] === 'unhealthy') {
                return 'unhealthy';
            }
        }
        return 'healthy';
    }
}
