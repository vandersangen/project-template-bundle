<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlag;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagAudience;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagAudienceRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagRepository;

class FeatureFlagRepositoryTest extends KernelTestCase
{
    private FeatureFlagRepository $featureFlagRepository;
    private FeatureFlagAudienceRepository $featureFlagAudienceRepository;
    private EntityManagerInterface $entityManager;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->featureFlagRepository = $container->get(FeatureFlagRepository::class);
        $this->featureFlagAudienceRepository = $container->get(FeatureFlagAudienceRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        $this->connection->executeStatement('DELETE FROM feature_flags');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM feature_flags');

        parent::tearDown();
    }

    public function testFindFlagByCidFindsAFlag(): void
    {
        $this->createFlag('a', 'Flag A');

        $flag = $this->featureFlagRepository->findFlagByCid('a');

        $this->assertNotNull($flag);
        $this->assertSame('Flag A', $flag->getName());
    }

    public function testFindFlagByCidReturnsNullForAnUnknownCid(): void
    {
        $this->assertNull($this->featureFlagRepository->findFlagByCid('nope'));
    }

    public function testFindAllFlagsIsSortedByCid(): void
    {
        $this->createFlag('c', 'C');
        $this->createFlag('a', 'A');
        $this->createFlag('b', 'B');

        $flags = $this->featureFlagRepository->findAllFlags();
        $cids = array_map(static fn (FeatureFlag $flag): string => $flag->getCid(), $flags);

        $this->assertSame(['a', 'b', 'c'], $cids);
    }

    public function testFindAllStatesMapsCidToAudienceToIsOn(): void
    {
        $flag = $this->createFlag('a', 'A');
        $this->createSwitch($flag, 'klanten', true);

        $this->assertSame(['a' => ['klanten' => true]], $this->featureFlagAudienceRepository->findAllStates());
    }

    public function testFindAllStatesIsEmptyWithoutRows(): void
    {
        $this->createFlag('a', 'A');

        $this->assertSame([], $this->featureFlagAudienceRepository->findAllStates());
    }

    public function testFindAllSwitchesReturnsEveryRowWithItsFlag(): void
    {
        $flag = $this->createFlag('a', 'A');
        $this->createSwitch($flag, 'klanten', true);
        $this->createSwitch($flag, 'beheer', false);

        $switches = $this->featureFlagAudienceRepository->findAllSwitches();

        $this->assertCount(2, $switches);
        $this->assertSame('a', $switches[0]->getFeatureFlag()->getCid());
    }

    public function testFindSwitchOfForUpdateFindsTheRowInsideATransaction(): void
    {
        $flag = $this->createFlag('a', 'A');
        $this->createSwitch($flag, 'klanten', true);

        $this->entityManager->getConnection()->beginTransaction();
        $found = $this->featureFlagAudienceRepository->findSwitchOfForUpdate((int) $flag->getId(), 'klanten');
        $missing = $this->featureFlagAudienceRepository->findSwitchOfForUpdate((int) $flag->getId(), 'beheer');
        $this->entityManager->getConnection()->rollBack();

        $this->assertNotNull($found);
        $this->assertTrue($found->isOn());
        $this->assertNull($missing);
    }

    public function testDeletingAFlagRemovesItsSwitches(): void
    {
        $flag = $this->createFlag('a', 'A');
        $this->createSwitch($flag, 'klanten', true);

        $this->entityManager->remove($flag);
        $this->entityManager->flush();

        $count = $this->connection->fetchOne('SELECT COUNT(1) FROM feature_flag_audiences');

        $this->assertSame(0, (int) $count);
    }

    public function testASecondRowForTheSameFlagAndAudienceIsRefused(): void
    {
        $flag = $this->createFlag('a', 'A');
        $this->createSwitch($flag, 'klanten', true);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createSwitch($flag, 'klanten', false);
    }

    private function createFlag(string $cid, string $name): FeatureFlag
    {
        $flag = new FeatureFlag();
        $flag->setCid($cid);
        $flag->setName($name);

        $this->entityManager->persist($flag);
        $this->entityManager->flush();

        return $flag;
    }

    private function createSwitch(FeatureFlag $flag, string $audience, bool $on): void
    {
        $this->entityManager->persist(new FeatureFlagAudience($flag, $audience, $on, 'tester'));
        $this->entityManager->flush();
    }
}
