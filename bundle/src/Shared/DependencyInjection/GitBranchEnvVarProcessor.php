<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\Shared\DependencyInjection;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * Custom environment variable processor that generates database names based on Git branch.
 *
 * Usage in .env or doctrine.yaml:
 * DATABASE_URL="mysql://user:password@db:3306/%env(git_branch_db:DATABASE_NAME)%?serverVersion=8.0&charset=utf8mb4"
 *
 * Where DATABASE_NAME is a base name like "app_db"
 */
class GitBranchEnvVarProcessor implements EnvVarProcessorInterface
{
    private const string DEFAULT_BRANCH = 'main';
    private const string DB_PREFIX = 'app_db_';

    /**
     * Get the current Git branch name, sanitized for use in database names.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function getCurrentBranch(): string
    {
        // Try to get branch from git command
        $branch = $this->getBranchFromGit();

        if ($branch === null) {
            // Fallback to environment variable if set
            $branch = $_ENV['GIT_BRANCH'] ?? $_SERVER['GIT_BRANCH'] ?? null;
        }

        if ($branch === null || $branch === '') {
            $branch = self::DEFAULT_BRANCH;
        }

        return $this->sanitizeBranchName($branch);
    }

    /**
     * Get branch name from git command.
     *
     * @SuppressWarnings(PHPMD.ErrorControlOperator)
     */
    private function getBranchFromGit(): ?string
    {
        // Check if we're in a git repository
        $gitDir = getcwd();
        while ($gitDir !== '/' && !is_dir($gitDir . '/.git')) {
            $gitDir = dirname($gitDir);
        }

        if (!is_dir($gitDir . '/.git')) {
            return null;
        }

        // Try to get current branch
        $branch = @shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null');

        if ($branch === null || $branch === false) {
            return null;
        }

        return trim($branch);
    }

    /**
     * Sanitize branch name for use in database names.
     * Replaces special characters with underscores.
     */
    private function sanitizeBranchName(string $branch): string
    {
        // Replace slashes, dashes, and other special characters with underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $branch);

        // Remove multiple consecutive underscores
        $sanitized = preg_replace('/_+/', '_', (string) $sanitized);

        // Remove leading/trailing underscores
        $sanitized = trim((string) $sanitized, '_');

        // Convert to lowercase for consistency
        return strtolower($sanitized);
    }

    /**
     * Process the environment variable.
     *
     * @param string   $prefix The processor prefix (git_branch_db).
     * @param string   $name   The base database name.
     * @param \Closure $getEnv Closure to get environment variables.
     *
     * @return string The processed database name.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        // Get the base name from the environment variable
        $baseName = $getEnv($name);

        // Get current branch
        $branch = $this->getCurrentBranch();

        // If base name already has the prefix, use it as is
        if (str_starts_with((string) $baseName, self::DB_PREFIX)) {
            // Extract the suffix after the prefix
            $suffix = substr((string) $baseName, strlen(self::DB_PREFIX));
            if ($suffix !== '') {
                return self::DB_PREFIX . $branch . '_' . $suffix;
            }
        }

        // Otherwise, create the database name with branch
        return self::DB_PREFIX . $branch;
    }

    /**
     * Get the supported prefixes.
     *
     * @return string[]
     */
    public static function getProvidedTypes(): array
    {
        return ['git_branch_db' => 'string'];
    }
}
