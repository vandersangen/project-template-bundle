<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\DependencyInjection;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use VanDerSangen\ProjectTemplateBundle\DependencyInjection\ProjectTemplateExtension;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\DependencyInjection\RequireFeatureFlagAudienceResolverPass;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceResolverInterface;
use VanDerSangen\ProjectTemplateBundle\ProjectTemplateBundle;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudienceResolver;

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

    public function testWithoutFeatureFlagConfigurationThereAreNoAudiences(): void
    {
        $this->extension->load([], $this->container);

        $this->assertSame([], $this->container->getParameter('project_template.feature_flags.audiences'));
    }

    public function testTheConfiguredAudiencesAreAParameterInConfigOrder(): void
    {
        $this->extension->load(
            $this->audienceConfig([
                'medewerkers' => ['label' => 'Medewerkers'],
                'klanten' => ['label' => 'Klanten'],
                'gasten' => ['label' => 'Niet ingelogd', 'guest' => true],
            ]),
            $this->container,
        );

        $this->assertSame(
            [
                'medewerkers' => ['label' => 'Medewerkers', 'guest' => false],
                'klanten' => ['label' => 'Klanten', 'guest' => false],
                'gasten' => ['label' => 'Niet ingelogd', 'guest' => true],
            ],
            $this->container->getParameter('project_template.feature_flags.audiences'),
        );
    }

    /**
     * @return iterable<string, array{0: array<string, array<string, mixed>>, 1: string}>
     */
    public static function invalidAudiences(): iterable
    {
        yield 'a capital in the key' => [['Klanten' => ['label' => 'Klanten']], 'Klanten'];
        yield 'a key of 21 characters' => [[str_repeat('a', 21) => ['label' => 'Lang']], str_repeat('a', 21)];
        yield 'only digits' => [['2026' => ['label' => 'Jaar']], '2026'];
        yield 'a digit first' => [['1abc' => ['label' => 'Cijfer']], '1abc'];
        yield 'an underscore first' => [['_klanten' => ['label' => 'Klanten']], '_klanten'];
        yield 'an empty label' => [['klanten' => ['label' => '']], 'label'];
        yield 'no label' => [['klanten' => []], 'label'];
        yield 'two guest audiences' => [
            [
                'gasten' => ['label' => 'Gasten', 'guest' => true],
                'bezoekers' => ['label' => 'Bezoekers', 'guest' => true],
            ],
            'guest',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $audiences
     */
    #[DataProvider('invalidAudiences')]
    public function testTheBuildFailsOnAnInvalidAudience(array $audiences, string $expectedInMessage): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedInMessage);

        $this->extension->load($this->audienceConfig($audiences), $this->container);
    }

    public function testAKeyOfTwentyCharactersIsAccepted(): void
    {
        $this->extension->load(
            $this->audienceConfig([str_repeat('a', 20) => ['label' => 'Twintig']]),
            $this->container,
        );

        $this->assertArrayHasKey(
            str_repeat('a', 20),
            $this->container->getParameter('project_template.feature_flags.audiences'),
        );
    }

    public function testAudiencesWithoutAResolverFailTheBuild(): void
    {
        $this->extension->load($this->audienceConfig(['klanten' => ['label' => 'Klanten']]), $this->container);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf(
            '%s is set, so a service for %s is required (docs/feature-flags.md).',
            'project_template.feature_flags.audiences',
            'FeatureFlagAudienceResolverInterface',
        ));

        new RequireFeatureFlagAudienceResolverPass()->process($this->container);
    }

    public function testAudiencesWithAResolverBuild(): void
    {
        $this->extension->load($this->audienceConfig(['klanten' => ['label' => 'Klanten']]), $this->container);
        $this->container->register(TestFeatureFlagAudienceResolver::class);
        $this->container->setAlias(
            FeatureFlagAudienceResolverInterface::class,
            TestFeatureFlagAudienceResolver::class,
        );

        new RequireFeatureFlagAudienceResolverPass()->process($this->container);

        $this->addToAssertionCount(1);
    }

    public function testNoAudiencesAndNoResolverBuild(): void
    {
        $this->extension->load([], $this->container);

        new RequireFeatureFlagAudienceResolverPass()->process($this->container);

        $this->assertFalse($this->container->has(FeatureFlagAudienceResolverInterface::class));
    }

    public function testTheBundleRegistersTheResolverPass(): void
    {
        $container = new ContainerBuilder();
        new ProjectTemplateBundle()->build($container);

        $passes = array_map(
            static fn (CompilerPassInterface $pass): string => $pass::class,
            $container->getCompilerPassConfig()->getPasses(),
        );

        $this->assertContains(RequireFeatureFlagAudienceResolverPass::class, $passes);
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
     * @param array<string, array<string, mixed>> $audiences
     *
     * @return list<array<string, mixed>>
     */
    private function audienceConfig(array $audiences): array
    {
        return [['feature_flags' => ['audiences' => $audiences]]];
    }
}
