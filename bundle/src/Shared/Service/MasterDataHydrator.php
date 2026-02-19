<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\Shared\Service;

use Doctrine\Persistence\ObjectManager;
use RuntimeException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Service for hydrating entities from master data configuration arrays.
 * Automatically creates entities based on 'class' property in configuration.
 * Supports entity relationships via cid/guid references.
 */
class MasterDataHydrator
{
    public function __construct(
        private readonly ObjectManager $objectManager,
        private readonly EntityReferenceRegistry $referenceRegistry,
        private readonly ?UserPasswordHasherInterface $passwordHasher = null
    ) {
    }

    /**
     * Hydrate and persist entities from configuration.
     *
     * @param string                           $entityType The entity type (e.g., 'users', 'orders').
     * @param array<int, array<string, mixed>> $items      Configuration items with 'class' property.
     * @param array<string, mixed>             $options    Hydration options (e.g., uniqueFields, passwordField).
     * @param bool                             $flush      Whether to flush changes immediately (default: false).
     *
     * @return int Number of entities created.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function hydrateAndPersist(string $entityType, array $items, array $options = [], bool $flush = false): int
    {
        $created = 0;

        foreach ($items as $itemData) {
            if (!isset($itemData['class'])) {
                continue;
            }

            $entityClass = $itemData['class'];

            if (!class_exists($entityClass)) {
                continue;
            }

            // Check if entity already exists
            if ($this->entityExists($entityClass, $itemData, $options)) {
                continue;
            }

            // Create and hydrate entity (without relationships first)
            $entity = $this->hydrateEntity($entityClass, $itemData, $options, false);

            if ($entity !== null) {
                $this->objectManager->persist($entity);

                // Register entity in reference registry
                if (isset($itemData['cid'])) {
                    $this->referenceRegistry->registerByCid($entityType, $itemData['cid'], $entity);
                }
                if (isset($itemData['guid'])) {
                    $this->referenceRegistry->registerByGuid($itemData['guid'], $entity);
                }

                $created++;
            }
        }

        if ($flush && $created > 0) {
            $this->objectManager->flush();
        }

        return $created;
    }

    /**
     * Resolve and set entity relationships after all entities are created.
     *
     * @param string                           $entityType The entity type (e.g., 'users', 'orders').
     * @param array<int, array<string, mixed>> $items      Configuration items with 'class' property.
     */
    public function resolveRelationships(string $entityType, array $items): void
    {
        foreach ($items as $itemData) {
            if (!isset($itemData['class'])) {
                continue;
            }

            // Find the entity by its cid (if it has one)
            $entity = null;
            if (isset($itemData['cid'])) {
                $entity = $this->referenceRegistry->findByCid($entityType, $itemData['cid']);
            }

            if ($entity === null) {
                continue;
            }

            // Set relationships
            $this->setRelationships($entity, $itemData, $entityType);
        }

        $this->objectManager->flush();
    }

    /**
     * Check if entity already exists based on unique fields.
     */
    private function entityExists(string $entityClass, array $itemData, array $options): bool
    {
        $uniqueFields = $options['uniqueFields'] ?? [];
        
        if (empty($uniqueFields)) {
            return false;
        }

        $repository = $this->objectManager->getRepository($entityClass);
        $criteria = [];

        foreach ($uniqueFields as $field) {
            if (isset($itemData[$field])) {
                $criteria[$field] = $itemData[$field];
            }
        }

        if (empty($criteria)) {
            return false;
        }

        $qb = $repository->createQueryBuilder('e');
        $index = 0;
        
        foreach ($criteria as $field => $value) {
            $paramName = 'param' . $index;
            $qb->andWhere("e.$field = :$paramName")
               ->setParameter($paramName, $value);
            $index++;
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /**
     * Hydrate entity from array data.
     *
     * @param string               $entityClass          The entity class name.
     * @param array<string, mixed> $itemData             The item data.
     * @param array<string, mixed> $options              Hydration options.
     * @param bool                 $includeRelationships Whether to process relationship references.
     *
     * @return object|null The hydrated entity or null on failure.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function hydrateEntity(
        string $entityClass,
        array $itemData,
        array $options,
        bool $includeRelationships = true
    ): ?object {
        try {
            $entity = new $entityClass();

            foreach ($itemData as $property => $value) {
                // Skip special properties
                if (in_array($property, ['class', 'cid', 'guid'], true)) {
                    continue;
                }

                // Skip relationships if not including them
                if (!$includeRelationships && is_array($value) && (isset($value['cid']) || isset($value['guid']))) {
                    continue;
                }

                // Handle password hashing
                if ($property === ($options['passwordField'] ?? 'password')
                    && $this->passwordHasher !== null
                    && $entity instanceof PasswordAuthenticatedUserInterface
                ) {
                    $value = $this->passwordHasher->hashPassword($entity, $value);
                }

                // Set property using setter method
                $setter = 'set' . ucfirst((string) $property);
                if (method_exists($entity, $setter)) {
                    $entity->$setter($value);
                }
            }

            return $entity;
        } catch (\Throwable) {
            // Log error or handle gracefully
            return null;
        }
    }

    /**
     * Set relationships on an entity based on cid/guid references.
     *
     * @param object               $entity     The entity to set relationships on.
     * @param array<string, mixed> $itemData   The item data with relationship references.
     * @param string               $entityType The entity type.
     *
     * @throws \RuntimeException If referenced entity cannot be resolved.
     */
    private function setRelationships(object $entity, array $itemData, string $entityType): void
    {
        foreach ($itemData as $property => $value) {
            // Skip non-relationship properties
            if (!is_array($value) || (!isset($value['cid']) && !isset($value['guid']))) {
                continue;
            }

            // Resolve the referenced entity
            $referencedEntity = $this->resolveReference($value, $entityType);

            if ($referencedEntity === null) {
                $referenceInfo = isset($value['cid'])
                    ? "cid '{$value['cid']}'"
                    : "guid '{$value['guid']}'";

                throw new RuntimeException(
                    sprintf(
                        'Failed to resolve relationship "%s" for entity "%s". Referenced entity with %s not found.',
                        $property,
                        $entity::class,
                        $referenceInfo
                    )
                );
            }

            // Set the relationship
            $setter = 'set' . ucfirst((string) $property);
            if (method_exists($entity, $setter)) {
                $entity->$setter($referencedEntity);
            }
        }
    }

    /**
     * Resolve an entity reference by cid or guid.
     */
    private function resolveReference(array $reference, string $currentEntityType): ?object
    {
        if (isset($reference['cid'])) {
            // Determine target entity type (default to current if not specified)
            $targetEntityType = $reference['entityType'] ?? $currentEntityType;
            return $this->referenceRegistry->findByCid($targetEntityType, $reference['cid']);
        }

        if (isset($reference['guid'])) {
            return $this->referenceRegistry->findByGuid($reference['guid']);
        }

        return null;
    }
}
