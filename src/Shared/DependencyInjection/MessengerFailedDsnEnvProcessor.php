<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shared\DependencyInjection;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * Environment variable processor that derives the failed transport DSN
 * from MESSENGER_TRANSPORT_DSN by adding queue_name=failed to the query string.
 *
 * Usage in config: %env(messenger_failed_dsn:MESSENGER_TRANSPORT_DSN)%
 */
class MessengerFailedDsnEnvProcessor implements EnvVarProcessorInterface
{
    private const string QUEUE_NAME = 'failed';

    /**
     * @param string $prefix
     * @param string $name
     * @param \Closure $getEnv
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        try {
            $dsn = (string) $getEnv($name);
        } catch (\Throwable) {
            $dsn = '';
        }
        if ($dsn === '') {
            return 'doctrine://default?queue_name=' . rawurlencode(self::QUEUE_NAME) . '&auto_setup=1';
        }

        return $this->addQueueNameToDsn($dsn, self::QUEUE_NAME);
    }

    public static function getProvidedTypes(): array
    {
        return ['messenger_failed_dsn' => 'string'];
    }

    private function addQueueNameToDsn(string $dsn, string $queueName): string
    {
        $parts = explode('?', $dsn, 2);

        if (count($parts) === 1) {
            return $dsn . '?queue_name=' . rawurlencode($queueName) . '&auto_setup=1';
        }

        [$schemeAndHost, $query] = $parts;
        parse_str($query, $params);
        $params['queue_name'] = $queueName;
        $params['auto_setup'] = '1';

        return $schemeAndHost . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
    }
}
