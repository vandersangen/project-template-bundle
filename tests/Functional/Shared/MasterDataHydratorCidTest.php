<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\Shared;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Bundle\DoctrineBundle\Mapping\MappingDriver;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use VanDerSangen\ProjectTemplateBundle\Shared\Service\EntityReferenceRegistry;
use VanDerSangen\ProjectTemplateBundle\Shared\Service\MasterDataHydrator;
use VanDerSangen\ProjectTemplateBundle\Tests\Fixtures\Entity\CidTestItem;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;

class MasterDataHydratorCidTest extends KernelTestCase
{
    private const TEST_EMAIL = 'cid-test@example.com';

    private MasterDataHydrator $hydrator;
    private EntityReferenceRegistry $referenceRegistry;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->hydrator = $container->get(MasterDataHydrator::class);
        $this->referenceRegistry = $container->get(EntityReferenceRegistry::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $this->mapCidTestItem();

        $this->entityManager->getConnection()->executeStatement('DROP TABLE IF EXISTS cid_test_items');

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([$this->entityManager->getClassMetadata(CidTestItem::class)]);
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->executeStatement('DROP TABLE IF EXISTS cid_test_items');

        parent::tearDown();
    }

    /**
     * CidTestItem is mapped only for this test, so doctrine:schema:validate does not expect its table.
     */
    private function mapCidTestItem(): void
    {
        $driver = $this->entityManager->getConfiguration()->getMetadataDriverImpl();

        $this->assertInstanceOf(MappingDriver::class, $driver);

        $chain = $driver->getDriver();

        $this->assertInstanceOf(MappingDriverChain::class, $chain);

        $namespace = 'VanDerSangen\\ProjectTemplateBundle\\Tests\\Fixtures\\Entity';

        $chain->addDriver(new AttributeDriver([__DIR__ . '/../../Fixtures/Entity']), $namespace);
    }

    public function testStoresTheCidOfANewEntity(): void
    {
        $this->hydrator->hydrateAndPersist('cid_test_items', [$this->item('a', 'A')], [], true);

        $rows = $this->fetchRows();

        $this->assertSame([['cid' => 'a', 'name' => 'A']], $rows);
    }

    public function testFindsTheEntityByCidAndUpdatesIt(): void
    {
        $this->hydrator->hydrateAndPersist('cid_test_items', [$this->item('a', 'A')], [], true);
        $this->startNewLoad();

        $this->hydrator->hydrateAndPersist('cid_test_items', [$this->item('a', 'B')], [], true);

        $rows = $this->fetchRows();

        $this->assertSame([['cid' => 'a', 'name' => 'B']], $rows);
    }

    public function testAChangedCidMakesANewRow(): void
    {
        $this->hydrator->hydrateAndPersist('cid_test_items', [$this->item('a', 'A')], [], true);
        $this->startNewLoad();

        $this->hydrator->hydrateAndPersist('cid_test_items', [$this->item('b', 'A')], [], true);

        $this->assertCount(2, $this->fetchRows());
    }

    public function testAnEntityWithoutACidFieldIsNotSearchedByCid(): void
    {
        $this->deleteTestUser();
        $item = [
            'class' => User::class,
            'cid' => 'user-1',
            'email' => self::TEST_EMAIL,
            'firstName' => 'Cid',
            'lastName' => 'Test',
            'password' => 'secret',
        ];

        $this->hydrator->hydrateAndPersist('users', [$item], ['uniqueFields' => ['email']], true);
        $this->startNewLoad();
        $this->hydrator->hydrateAndPersist('users', [$item], ['uniqueFields' => ['email']], true);

        $count = $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(1) FROM users WHERE email = :email',
            ['email' => self::TEST_EMAIL]
        );
        $this->deleteTestUser();

        $this->assertSame(1, (int) $count);
    }

    private function deleteTestUser(): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'DELETE FROM users WHERE email = :email',
            ['email' => self::TEST_EMAIL]
        );
    }

    /**
     * Forgets what the previous master data load registered, as a new process would.
     */
    private function startNewLoad(): void
    {
        $this->entityManager->clear();
        $this->referenceRegistry->clear();
    }

    /**
     * @return array<string, string>
     */
    private function item(string $cid, string $name): array
    {
        return ['class' => CidTestItem::class, 'cid' => $cid, 'name' => $name];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRows(): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT cid, name FROM cid_test_items ORDER BY cid'
        );
    }
}
