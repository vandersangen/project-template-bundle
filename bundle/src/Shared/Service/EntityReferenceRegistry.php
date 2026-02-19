<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\Shared\Service;

use RuntimeException;

/**
 * Registry for tracking entities by their configuration identifiers (cid) and GUIDs.
 * Enables entity relationship resolution during master data loading.
 */
class EntityReferenceRegistry
{
    /**
     * @var array<string, array<string, object>> Map of entityType => [cid => entity]
     */
    private array $cidRegistry = [];

    /**
     * @var array<string, object> Map of guid => entity
     */
    private array $guidRegistry = [];

    /**
     * Register an entity with its cid (Configuration ID).
     * CID is unique within the same entity type.
     *
     * @param string $entityType The entity type (e.g., 'users', 'orders').
     * @param string $cid        The configuration identifier.
     * @param object $entity     The entity instance.
     *
     * @throws \RuntimeException If duplicate cid is found.
     */
    public function registerByCid(string $entityType, string $cid, object $entity): void
    {
        if (!isset($this->cidRegistry[$entityType])) {
            $this->cidRegistry[$entityType] = [];
        }

        if (isset($this->cidRegistry[$entityType][$cid])) {
            throw new RuntimeException(
                sprintf(
                    'Duplicate cid "%s" found for entity type "%s". CID must be unique within the same entity type.',
                    $cid,
                    $entityType
                )
            );
        }

        $this->cidRegistry[$entityType][$cid] = $entity;
    }

    /**
     * Register an entity with its GUID (Globally Unique Identifier).
     *
     * @param string $guid   The globally unique identifier.
     * @param object $entity The entity instance.
     *
     * @throws \RuntimeException If duplicate guid is found.
     */
    public function registerByGuid(string $guid, object $entity): void
    {
        if (isset($this->guidRegistry[$guid])) {
            throw new RuntimeException(
                sprintf(
                    'Duplicate guid "%s" found. GUID must be globally unique.',
                    $guid
                )
            );
        }

        $this->guidRegistry[$guid] = $entity;
    }

    /**
     * Find an entity by its cid within a specific entity type.
     *
     * @param string $entityType The entity type (e.g., 'users', 'orders').
     * @param string $cid        The configuration identifier.
     *
     * @return object|null The entity instance or null if not found.
     */
    public function findByCid(string $entityType, string $cid): ?object
    {
        return $this->cidRegistry[$entityType][$cid] ?? null;
    }

    /**
     * Find an entity by its GUID.
     *
     * @param string $guid The globally unique identifier.
     *
     * @return object|null The entity instance or null if not found.
     */
    public function findByGuid(string $guid): ?object
    {
        return $this->guidRegistry[$guid] ?? null;
    }

    /**
     * Check if a cid exists for a specific entity type.
     */
    public function hasCid(string $entityType, string $cid): bool
    {
        return isset($this->cidRegistry[$entityType][$cid]);
    }

    /**
     * Check if a guid exists.
     */
    public function hasGuid(string $guid): bool
    {
        return isset($this->guidRegistry[$guid]);
    }

    /**
     * Clear all registered entities.
     */
    public function clear(): void
    {
        $this->cidRegistry = [];
        $this->guidRegistry = [];
    }

    /**
     * Get all registered entity types.
     *
     * @return array<int, string>
     */
    public function getRegisteredEntityTypes(): array
    {
        return array_keys($this->cidRegistry);
    }

    /**
     * Get count of registered entities for a specific entity type.
     */
    public function getCountForEntityType(string $entityType): int
    {
        return count($this->cidRegistry[$entityType] ?? []);
    }

    /**
     * Get total count of all registered entities.
     */
    public function getTotalCount(): int
    {
        $total = 0;
        foreach ($this->cidRegistry as $entities) {
            $total += count($entities);
        }
        return $total;
    }
}
