<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Routing;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Service used with routes.yaml "type: service" to load the bundle's routes.
 * Method signature matches ObjectLoader's call: (Loader $loader, ?string $env).
 */
final readonly class ProjectTemplateRouteLoader
{
    private const string BUNDLE_NAME = 'ProjectTemplateBundle';

    public function __construct(
        private KernelInterface $kernel,
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function loadRoutes(LoaderInterface $loader, ?string $env = null): RouteCollection
    {
        $bundlePath = $this->kernel->getBundle(self::BUNDLE_NAME)->getPath();
        $path = $bundlePath . '/config/routes.yaml';
        return $loader->import($path, 'yaml');
    }
}
