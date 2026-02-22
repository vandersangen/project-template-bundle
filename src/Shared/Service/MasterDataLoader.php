<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shared\Service;

/**
 * Service for loading master data configurations from module directories.
 * Scans all modules for master_data directories and loads PHP configuration files.
 * Handles environment-specific configuration merging.
 */
class MasterDataLoader
{
    private readonly string $srcDir;
    private readonly string $bundleSrcDir;
    private ?array $cachedConfig = null;
    private ?array $cachedModulePaths = null;

    public function __construct(
        string $projectDir,
        private readonly string $environment
    ) {
        $this->srcDir = $projectDir . '/src';
        // Bundle source directory - look for master data in the bundle itself
        $this->bundleSrcDir = dirname(__DIR__, 2);
    }

    /**
     * Load master data configuration for the current environment.
     * Scans all modules and merges default configuration with environment-specific configuration.
     *
     * @return array<string, array<int, array<string, mixed>>>
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function loadConfiguration(): array
    {
        if ($this->cachedConfig !== null) {
            return $this->cachedConfig;
        }

        $config = [];

        // Scan all modules for master_data directories
        $modulePaths = $this->scanModuleMasterDataDirectories();

        foreach ($modulePaths as $moduleName => $masterDataDir) {
            // Load default configuration (always loaded)
            $defaultConfig = $this->loadPhpConfigFile($masterDataDir . '/default.php');
            $config = $this->mergeConfigurations($config, $defaultConfig);

            // Load environment-specific configuration if it exists
            $envConfigFile = $masterDataDir . '/' . $this->environment . '.php';
            if (file_exists($envConfigFile)) {
                $envConfig = $this->loadPhpConfigFile($envConfigFile);
                $config = $this->mergeConfigurations($config, $envConfig);
            }
        }

        $this->cachedConfig = $config;
        return $config;
    }

    /**
     * Get configuration for a specific module (e.g., 'users', 'roles').
     *
     * @return array<int, array<string, mixed>>
     */
    public function getModuleConfiguration(string $module): array
    {
        $config = $this->loadConfiguration();
        return $config[$module] ?? [];
    }

    /**
     * Check if a module has configuration.
     */
    public function hasModuleConfiguration(string $module): bool
    {
        $config = $this->loadConfiguration();
        return isset($config[$module]) && !empty($config[$module]);
    }

    /**
     * Get all configured modules (entity types, not module names).
     *
     * @return array<int, string>
     */
    public function getConfiguredModules(): array
    {
        $config = $this->loadConfiguration();
        return array_keys($config);
    }

    /**
     * Get all module names that have master_data directories.
     *
     * @return array<int, string>
     */
    public function getModulesWithMasterData(): array
    {
        $modulePaths = $this->scanModuleMasterDataDirectories();
        return array_keys($modulePaths);
    }

    /**
     * Scan all module directories for .config/master_data subdirectories.
     *
     * @return array<string, string> Map of module name => master_data directory path
     */
    private function scanModuleMasterDataDirectories(): array
    {
        if ($this->cachedModulePaths !== null) {
            return $this->cachedModulePaths;
        }

        $modulePaths = [];
        $srcDirectories = [$this->bundleSrcDir, $this->srcDir];

        foreach ($srcDirectories as $srcDir) {
            $modulePaths = array_merge($modulePaths, $this->scanSingleSrcDirectory($srcDir));
        }

        $this->cachedModulePaths = $modulePaths;
        return $modulePaths;
    }

    /**
     * Scan a single src directory for modules with master_data subdirectories.
     *
     * @return array<string, string> Map of module name => master_data directory path
     */
    private function scanSingleSrcDirectory(string $srcDir): array
    {
        if (!is_dir($srcDir)) {
            return [];
        }

        $directories = scandir($srcDir);
        if ($directories === false) {
            return [];
        }

        $skipEntries = ['.', '..', 'Kernel.php', 'ProjectTemplateBundle.php', 'DependencyInjection'];
        $modulePaths = [];

        foreach ($directories as $dir) {
            if (in_array($dir, $skipEntries, true)) {
                continue;
            }

            $modulePath = $srcDir . '/' . $dir;
            if (!is_dir($modulePath)) {
                continue;
            }

            $masterDataDir = $modulePath . '/.config/master_data';
            if (is_dir($masterDataDir)) {
                $moduleKey = basename($srcDir) === 'src' ? $dir : 'bundle_' . $dir;
                $modulePaths[$moduleKey] = $masterDataDir;
            }
        }

        return $modulePaths;
    }

    /**
     * Load a PHP configuration file.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function loadPhpConfigFile(string $filepath): array
    {
        if (!file_exists($filepath)) {
            return [];
        }

        $content = include $filepath;

        if (!is_array($content)) {
            return [];
        }

        return $content;
    }

    /**
     * Merge two configuration arrays.
     * Environment-specific config is appended to default config.
     *
     * @param array<string, array<int, array<string, mixed>>> $base
     * @param array<string, array<int, array<string, mixed>>> $override
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function mergeConfigurations(array $base, array $override): array
    {
        foreach ($override as $module => $items) {
            if (!isset($base[$module])) {
                $base[$module] = [];
            }

            // Append items from override to base
            $base[$module] = array_merge($base[$module], $items);
        }

        return $base;
    }

    /**
     * Clear the cached configuration.
     * Useful for testing or when configuration files change.
     */
    public function clearCache(): void
    {
        $this->cachedConfig = null;
        $this->cachedModulePaths = null;
    }

    /**
     * Get the current environment.
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Get the source directory path.
     */
    public function getSrcDir(): string
    {
        return $this->srcDir;
    }
}
