<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\DependencyInjection;

use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use VanDerSangen\ProjectTemplateBundle\DependencyInjection\ProjectTemplateExtension;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\DuplicateKeyFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\ForeignBackgroundAudienceFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\LongKeyAudience;

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
        $this->assertArrayHasKey('ProjectTemplateBundleFeatureFlag', $mappings);
        $this->assertSame(
            'VanDerSangen\\ProjectTemplateBundle\\FeatureFlag\\Entity',
            $mappings['ProjectTemplateBundleFeatureFlag']['prefix'],
        );
    }

    public function testFeatureFlagParametersAreNullWithoutConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $this->assertNull($this->container->getParameter('project_template.feature_flags.flags'));
        $this->assertNull($this->container->getParameter('project_template.feature_flags.audiences'));
    }

    public function testFeatureFlagParametersCarryTheConfiguredEnumClasses(): void
    {
        $this->extension->load(
            $this->featureFlagConfig(TestFeatureFlag::class, TestFeatureFlagAudience::class),
            $this->container,
        );

        $this->assertSame(
            TestFeatureFlag::class,
            $this->container->getParameter('project_template.feature_flags.flags'),
        );
        $this->assertSame(
            TestFeatureFlagAudience::class,
            $this->container->getParameter('project_template.feature_flags.audiences'),
        );
    }

    public function testTheBuildFailsOnAFlagClassThatIsNoFeatureFlagEnum(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('project_template.feature_flags.flags');
        $this->expectExceptionMessage(stdClass::class);
        $this->expectExceptionMessage(FeatureFlagInterface::class);

        $this->extension->load(
            $this->featureFlagConfig(stdClass::class, TestFeatureFlagAudience::class),
            $this->container,
        );
    }

    public function testTheBuildFailsOnAnAudienceClassThatIsNoAudienceEnum(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('project_template.feature_flags.audiences');
        $this->expectExceptionMessage(FeatureFlagAudienceInterface::class);

        $this->extension->load(
            $this->featureFlagConfig(TestFeatureFlag::class, TestFeatureFlag::class),
            $this->container,
        );
    }

    public function testTheBuildFailsOnTwoFlagsWithTheSameKey(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(DuplicateKeyFeatureFlag::class);

        $this->extension->load(
            $this->featureFlagConfig(DuplicateKeyFeatureFlag::class, TestFeatureFlagAudience::class),
            $this->container,
        );
    }

    public function testTheBuildFailsOnFlagsWithoutAudiences(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('project_template.feature_flags.audiences');

        $this->extension->load($this->featureFlagConfig(TestFeatureFlag::class, null), $this->container);
    }

    public function testTheBuildFailsOnABackgroundAudienceOfAnotherEnum(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(ForeignBackgroundAudienceFeatureFlag::class);

        $this->extension->load(
            $this->featureFlagConfig(ForeignBackgroundAudienceFeatureFlag::class, TestFeatureFlagAudience::class),
            $this->container,
        );
    }

    public function testTheBuildFailsOnAnAudienceKeyThatDoesNotFitTheColumn(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(LongKeyAudience::class);

        $this->extension->load(
            $this->featureFlagConfig(TestFeatureFlag::class, LongKeyAudience::class),
            $this->container,
        );
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

    /**
     * @return list<array<string, mixed>>
     */
    private function featureFlagConfig(?string $flagEnumClass, ?string $audienceEnumClass): array
    {
        return [
            [
                'feature_flags' => [
                    'flags' => $flagEnumClass,
                    'audiences' => $audienceEnumClass,
                ],
            ],
        ];
    }
}
