<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\DataFixtures;

use LarsVanDerSangen\ProjectTemplateBundle\Shared\Service\EntityDependencyResolver;
use LarsVanDerSangen\ProjectTemplateBundle\Shared\Service\EntityReferenceRegistry;
use LarsVanDerSangen\ProjectTemplateBundle\Shared\Service\MasterDataHydrator;
use LarsVanDerSangen\ProjectTemplateBundle\Shared\Service\MasterDataLoader;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Generic fixture that automatically loads all master data configurations
 * that have a 'class' property defined.
 *
 * Supports entity relationships and dependency-based loading order.
 * This eliminates the need for entity-specific fixture classes.
 */
class GenericMasterDataFixtures extends Fixture
{
    public function __construct(
        private readonly MasterDataLoader $masterDataLoader,
        private readonly MasterDataHydrator $masterDataHydrator,
        private readonly EntityDependencyResolver $dependencyResolver,
        private readonly EntityReferenceRegistry $referenceRegistry
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @throws \RuntimeException
     */
    public function load(ObjectManager $manager): void
    {
        // Clear reference registry
        $this->referenceRegistry->clear();

        // Load configuration
        $config = $this->masterDataLoader->loadConfiguration();

        // Resolve loading order based on dependencies
        try {
            $loadingOrder = $this->dependencyResolver->resolveLoadingOrder($config);
        } catch (\RuntimeException $e) {
            // If circular dependency detected, show error and exit
            echo "\n[ERROR] " . $e->getMessage() . "\n\n";
            throw $e;
        }

        // Phase 1: Create all entities (without relationships, don't flush yet)
        foreach ($loadingOrder as $entityType) {
            $items = $config[$entityType] ?? [];

            if (empty($items)) {
                continue;
            }

            // Check if items have 'class' property
            $firstItem = reset($items);
            if (!isset($firstItem['class'])) {
                continue;
            }

            // Determine hydration options based on entity type
            $options = $this->getHydrationOptions($entityType, $firstItem['class']);

            // Hydrate and persist entities (without relationships, don't flush)
            $this->masterDataHydrator->hydrateAndPersist($entityType, $items, $options, false);
        }

        // Phase 2: Resolve relationships and flush
        foreach ($loadingOrder as $entityType) {
            $items = $config[$entityType] ?? [];

            if (empty($items)) {
                continue;
            }

            // Resolve and set relationships
            $this->masterDataHydrator->resolveRelationships($entityType, $items);
        }
    }

    /**
     * Get hydration options for specific entity types.
     *
     * @return array<string, mixed>
     */
    private function getHydrationOptions(string $entityType, string $entityClass): array
    {
        // Default options
        $options = [
            'uniqueFields' => [],
            'passwordField' => 'password',
        ];

        // Entity-specific options
        if ($entityType === 'users' || str_contains($entityClass, 'User')) {
            $options['uniqueFields'] = ['email'];
        }

        if ($entityType === 'orders' || str_contains($entityClass, 'Order')) {
            $options['uniqueFields'] = ['orderNumber'];
        }

        return $options;
    }
}
