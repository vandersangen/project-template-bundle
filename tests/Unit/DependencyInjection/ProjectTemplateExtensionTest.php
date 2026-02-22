<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use VanDerSangen\ProjectTemplateBundle\DependencyInjection\ProjectTemplateExtension;

class ProjectTemplateExtensionTest extends TestCase
{
    private ContainerBuilder $container;
    private ProjectTemplateExtension $extension;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->extension = new ProjectTemplateExtension();
    }

    public function testPrependRegistersBundleMigrationsPaths(): void
    {
        $this->extension->prepend($this->container);

        $configs = $this->container->getExtensionConfig('doctrine_migrations');

        $this->assertNotEmpty($configs, 'doctrine_migrations config should be prepended');

        $migrationsPaths = null;
        foreach ($configs as $config) {
            if (isset($config['migrations_paths'])) {
                $migrationsPaths = $config['migrations_paths'];
                break;
            }
        }

        $this->assertNotNull($migrationsPaths, 'migrations_paths should be configured');
        $this->assertArrayHasKey(
            'DoctrineMigrations\\ProjectTemplateBundle',
            $migrationsPaths,
            'Bundle migrations namespace should be registered'
        );

        $expectedPath = dirname(__DIR__, 3) . '/migrations';
        $this->assertEquals(
            $expectedPath,
            $migrationsPaths['DoctrineMigrations\\ProjectTemplateBundle'],
            'Migrations path should point to the bundle migrations directory'
        );
    }

    public function testPrependRegistersDoctrineEntityMappings(): void
    {
        $this->extension->prepend($this->container);

        $configs = $this->container->getExtensionConfig('doctrine');

        $this->assertNotEmpty($configs, 'doctrine config should be prepended');

        $mappings = null;
        foreach ($configs as $config) {
            if (isset($config['orm']['mappings'])) {
                $mappings = $config['orm']['mappings'];
                break;
            }
        }

        $this->assertNotNull($mappings, 'ORM mappings should be configured');
        $this->assertArrayHasKey('ProjectTemplateBundle', $mappings);
        $this->assertArrayHasKey('ProjectTemplateBundleMail', $mappings);
        $this->assertArrayHasKey('ProjectTemplateBundleQueue', $mappings);
    }

    public function testMigrationsPathDirectoryExists(): void
    {
        $migrationsDir = dirname(__DIR__, 3) . '/migrations';
        $this->assertDirectoryExists($migrationsDir, 'Migrations directory should exist');
    }

    public function testMigrationsDirectoryContainsMigrationFiles(): void
    {
        $migrationsDir = dirname(__DIR__, 3) . '/migrations';
        $files = glob($migrationsDir . '/Version*.php');

        $this->assertNotEmpty($files, 'Migrations directory should contain migration files');
    }

    public function testMigrationFilesUseCorrectNamespace(): void
    {
        $migrationsDir = dirname(__DIR__, 3) . '/migrations';
        $files = glob($migrationsDir . '/Version*.php');

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringContainsString(
                'namespace DoctrineMigrations\\ProjectTemplateBundle;',
                $content,
                sprintf('Migration file %s should use the correct namespace', basename($file))
            );
        }
    }
}
