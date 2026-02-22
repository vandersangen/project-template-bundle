<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shared\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use RuntimeException;

/**
 * Resolves entity dependencies by analyzing Doctrine metadata.
 * Determines the correct loading order for entities based on their relationships.
 */
class EntityDependencyResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Resolve loading order for entity types based on their dependencies.
     * Returns entity types sorted in dependency order (dependencies first).
     *
     * @param array<string, array<int, array<string, mixed>>> $config Configuration with entity types as keys.
     *
     * @return array<int, string> Sorted entity types in loading order.
     *
     * @throws \RuntimeException If circular dependency is detected.
     */
    public function resolveLoadingOrder(array $config): array
    {
        // Build dependency graph
        $dependencies = $this->buildDependencyGraph($config);

        // Perform topological sort
        return $this->topologicalSort($dependencies);
    }

    /**
     * Build dependency graph by analyzing entity relationships.
     *
     * @param array<string, array<int, array<string, mixed>>> $config
     *
     * @return array<string, array<int, string>> Map of entityType => [dependent entity types].
     */
    private function buildDependencyGraph(array $config): array
    {
        $dependencies = [];

        foreach ($config as $entityType => $items) {
            if (empty($items)) {
                continue;
            }

            $firstItem = reset($items);
            if (!isset($firstItem['class'])) {
                continue;
            }

            $entityClass = $firstItem['class'];
            if (!class_exists($entityClass)) {
                continue;
            }

            // Get entity metadata
            try {
                $metadata = $this->entityManager->getClassMetadata($entityClass);
            } catch (\Exception) {
                // Skip if metadata not available
                continue;
            }

            // Analyze associations
            $entityDependencies = $this->extractDependencies($metadata, $config);
            $dependencies[$entityType] = $entityDependencies;
        }

        return $dependencies;
    }

    /**
     * Extract dependencies from entity metadata.
     *
     * @param ClassMetadata $metadata
     * @param array<string, array<int, array<string, mixed>>> $config
     *
     * @return array<int, string> List of entity types this entity depends on.
     */
    private function extractDependencies(ClassMetadata $metadata, array $config): array
    {
        $dependencies = [];

        // Check ManyToOne and OneToOne associations (these are dependencies)
        foreach ($metadata->getAssociationMappings() as $association) {
            // Only consider associations where this entity has the foreign key
            if (
                isset($association['joinColumns'])
                || ($association['type'] === ClassMetadata::MANY_TO_ONE)
                || (
                    $association['type'] === ClassMetadata::ONE_TO_ONE
                    && isset($association['isOwningSide']) && $association['isOwningSide']
                )
            ) {
                $targetEntity = $association['targetEntity'];

                // Find which entity type in config corresponds to this target entity
                $dependentEntityType = $this->findEntityTypeForClass($targetEntity, $config);
                if ($dependentEntityType !== null && !in_array($dependentEntityType, $dependencies, true)) {
                    $dependencies[] = $dependentEntityType;
                }
            }
        }

        return $dependencies;
    }

    /**
     * Find entity type key for a given entity class.
     *
     * @param string $entityClass
     * @param array<string, array<int, array<string, mixed>>> $config
     *
     * @return string|null
     */
    private function findEntityTypeForClass(string $entityClass, array $config): ?string
    {
        foreach ($config as $entityType => $items) {
            if (empty($items)) {
                continue;
            }

            $firstItem = reset($items);
            if (isset($firstItem['class']) && $firstItem['class'] === $entityClass) {
                return $entityType;
            }
        }

        return null;
    }

    /**
     * Perform topological sort on dependency graph.
     *
     * @param array<string, array<int, string>> $dependencies
     *
     * @return array<int, string> Sorted entity types.
     *
     * @throws \RuntimeException If circular dependency detected.
     */
    private function topologicalSort(array $dependencies): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        foreach (array_keys($dependencies) as $entityType) {
            $this->visit($entityType, $dependencies, $visited, $visiting, $sorted);
        }

        return array_reverse($sorted);
    }

    /**
     * Visit node in dependency graph (DFS).
     *
     * @param string $entityType
     * @param array<string, array<int, string>> $dependencies
     * @param array<string, bool> $visited
     * @param array<string, bool> $visiting
     * @param array<int, string> $sorted
     *
     * @throws \RuntimeException If circular dependency detected.
     */
    private function visit(
        string $entityType,
        array $dependencies,
        array &$visited,
        array &$visiting,
        array &$sorted
    ): void {
        // Already processed
        if (isset($visited[$entityType])) {
            return;
        }

        // Circular dependency detected
        if (isset($visiting[$entityType])) {
            throw new RuntimeException(
                sprintf(
                    'Circular dependency detected for entity type "%s"',
                    $entityType
                )
            );
        }

        // Mark as currently visiting
        $visiting[$entityType] = true;

        // Visit dependencies first
        if (isset($dependencies[$entityType])) {
            foreach ($dependencies[$entityType] as $dependency) {
                $this->visit($dependency, $dependencies, $visited, $visiting, $sorted);
            }
        }

        // Mark as visited and add to sorted list
        unset($visiting[$entityType]);
        $visited[$entityType] = true;
        $sorted[] = $entityType;
    }
}
