<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlag;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagAudience;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\EventListener\FeatureFlagCacheInvalidationListener;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Exception\UnknownFeatureFlagException;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagAudienceRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;

class FeatureFlagServiceTest extends KernelTestCase
{
    private const string CACHE_KEY = 'project_template_feature_flag_states';

    private FeatureFlagService $featureFlagService;
    private FeatureFlagAudienceRepository $featureFlagAudienceRepository;
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private ?FeatureFlagChangeRecorder $recorder = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->featureFlagService = $container->get(FeatureFlagService::class);
        $this->featureFlagAudienceRepository = $container->get(FeatureFlagAudienceRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        $this->connection->executeStatement('DELETE FROM feature_flags');
        $this->featureFlagService->clearCachedStates();
    }

    protected function tearDown(): void
    {
        if ($this->recorder !== null) {
            $this->entityManager->getEventManager()->removeEventListener([Events::onFlush], $this->recorder);
            $this->recorder = null;
        }

        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->connection->executeStatement('DELETE FROM feature_flags');
        $this->featureFlagService->clearCachedStates();

        parent::tearDown();
    }

    public function testAFlagNeverSwitchedIsOffForEveryAudience(): void
    {
        $this->createFlag('a');

        $this->assertFalse($this->featureFlagService->isOnFor('a', 'klanten'));
        $this->assertFalse($this->featureFlagService->isOnFor('a', 'medewerkers'));
        $this->assertFalse($this->featureFlagService->isOnFor('a', 'gasten'));
        $this->assertFalse($this->featureFlagService->isOn('a'));
        $this->assertSame([], $this->featureFlagService->listCidsOn());
    }

    public function testSwitchingOnCountsForThatAudienceOnly(): void
    {
        $this->createFlag('a');

        $this->featureFlagService->switchFlag('a', 'klanten', true, 'rick@example.com');

        $this->assertTrue($this->featureFlagService->isOnFor('a', 'klanten'));
        $this->assertFalse($this->featureFlagService->isOnFor('a', 'medewerkers'));

        $switches = $this->featureFlagAudienceRepository->findAllSwitches();
        $this->assertCount(1, $switches);
        $this->assertSame('a', $switches[0]->getFeatureFlag()->getCid());
        $this->assertSame('klanten', $switches[0]->getAudience());
        $this->assertTrue($switches[0]->isOn());
        $this->assertSame('rick@example.com', $switches[0]->getUpdatedBy());
    }

    public function testSwitchingOffAgain(): void
    {
        $this->createFlag('a');

        $this->featureFlagService->switchFlag('a', 'klanten', true, 'rick@example.com');
        $this->featureFlagService->switchFlag('a', 'klanten', false, 'lars@example.com');

        $this->assertFalse($this->featureFlagService->isOnFor('a', 'klanten'));
        $this->assertSame(
            [['is_on' => 0, 'updated_by' => 'lars@example.com']],
            $this->connection->fetchAllAssociative('SELECT is_on, updated_by FROM feature_flag_audiences'),
        );
    }

    public function testAnAudienceThatIsNotConfiguredIsOffEvenWithARow(): void
    {
        $flag = $this->createFlag('a');
        $this->insertSwitch($flag, 'onbekend', true);

        $this->assertFalse($this->featureFlagService->isOnFor('a', 'onbekend'));
        $this->assertFalse($this->featureFlagService->isOnFor('b', 'klanten'));
    }

    public function testSwitchingAnUnknownFlagThrowsAndWritesNothing(): void
    {
        $this->expectException(UnknownFeatureFlagException::class);

        try {
            $this->featureFlagService->switchFlag('nope', 'klanten', true, 'rick@example.com');
        } finally {
            $this->assertSame(0, $this->countSwitchRows());
        }
    }

    public function testSwitchingForAnUnknownAudienceThrowsAndWritesNothing(): void
    {
        $this->createFlag('a');

        $this->expectException(UnknownFeatureFlagException::class);

        try {
            $this->featureFlagService->switchFlag('a', 'onbekend', true, 'rick@example.com');
        } finally {
            $this->assertSame(0, $this->countSwitchRows());
        }
    }

    public function testAnUpdatedByLongerThanTheColumnIsCutToItsFirst180Characters(): void
    {
        $this->createFlag('a');
        $updatedBy = str_repeat('a', 179) . 'é' . str_repeat('b', 20);

        $this->featureFlagService->switchFlag('a', 'klanten', true, $updatedBy);

        $this->assertSame(
            mb_substr($updatedBy, 0, 180),
            $this->connection->fetchOne('SELECT updated_by FROM feature_flag_audiences'),
        );
    }

    public function testSwitchingToTheSameStateTwiceWritesOnce(): void
    {
        $this->createFlag('a');
        $this->featureFlagService->switchFlag('a', 'klanten', true, 'rick@example.com');
        $this->connection->executeStatement("UPDATE feature_flag_audiences SET updated_at = '2026-10-19 12:00:00'");
        $before = $this->connection->fetchAssociative('SELECT * FROM feature_flag_audiences');

        $this->featureFlagService->switchFlag('a', 'klanten', true, 'lars@example.com');

        $this->assertSame($before, $this->connection->fetchAssociative('SELECT * FROM feature_flag_audiences'));
        $this->assertSame(1, $this->countSwitchRows());
        $this->assertTrue($this->featureFlagService->isOnFor('a', 'klanten'));
    }

    public function testAFirstSwitchToOffIsAnUpdateToOffForEntityListeners(): void
    {
        $this->createFlag('a');
        $recorder = $this->recordChanges();

        $this->featureFlagService->switchFlag('a', 'klanten', false, 'rick@example.com');

        $this->assertSame(0, $recorder->insertions);
        $this->assertSame([[true, false]], $recorder->updatesOfOn);
        $this->assertSame([[]], $recorder->rowsSeenOutside);
        $this->assertSame(
            [['audience' => 'klanten', 'is_on' => 0, 'updated_by' => 'rick@example.com']],
            $this->connection->fetchAllAssociative('SELECT audience, is_on, updated_by FROM feature_flag_audiences'),
        );
    }

    public function testAFirstSwitchToOnIsOneUpdateToOn(): void
    {
        $this->createFlag('a');
        $recorder = $this->recordChanges();

        $this->featureFlagService->switchFlag('a', 'klanten', true, 'rick@example.com');

        $this->assertSame(0, $recorder->insertions);
        $this->assertSame([[false, true]], $recorder->updatesOfOn);
        $this->assertSame([[]], $recorder->rowsSeenOutside);
    }

    public function testASwitchOfAnExistingRowIsSeenOutsideOnlyAsItsOldState(): void
    {
        $flag = $this->createFlag('a');
        $this->insertSwitch($flag, 'klanten', false);
        $recorder = $this->recordChanges();

        $this->featureFlagService->switchFlag('a', 'klanten', true, 'lars@example.com');

        $this->assertSame([[false, true]], $recorder->updatesOfOn);
        $this->assertSame([[['audience' => 'klanten', 'is_on' => 0]]], $recorder->rowsSeenOutside);
    }

    public function testTwoFirstSwitchesOfTheSameRowLeaveOneRowInTheStateOfTheSecond(): void
    {
        $this->createFlag('a');

        $this->featureFlagService->switchFlag('a', 'klanten', true, 'rick@example.com');
        $this->featureFlagService->switchFlag('a', 'klanten', false, 'lars@example.com');

        $this->assertSame(
            [['is_on' => 0, 'updated_by' => 'lars@example.com']],
            $this->connection->fetchAllAssociative('SELECT is_on, updated_by FROM feature_flag_audiences'),
        );
        $this->assertFalse($this->featureFlagService->isOnFor('a', 'klanten'));
    }

    public function testAFirstSwitchWhoseRowAnotherProcessStoredFirstIsAppliedAsAnUpdate(): void
    {
        $flag = $this->createFlag('a');
        $this->insertSwitch($flag, 'klanten', false);

        $this->featureFlagService->switchFlag('a', 'klanten', true, 'lars@example.com');

        $this->assertSame(
            [['is_on' => 1, 'updated_by' => 'lars@example.com']],
            $this->connection->fetchAllAssociative('SELECT is_on, updated_by FROM feature_flag_audiences'),
        );
        $this->assertTrue($this->featureFlagService->isOnFor('a', 'klanten'));
    }

    public function testAGuestCountsAsTheGuestAudience(): void
    {
        $this->createFlag('a');
        $this->createFlag('b');
        $this->featureFlagService->switchFlag('a', 'gasten', true, 'rick');
        $this->featureFlagService->switchFlag('b', 'klanten', true, 'rick');

        $this->assertTrue($this->featureFlagService->isOn('a'));
        $this->assertFalse($this->featureFlagService->isOn('b'));
        $this->assertSame(['a'], $this->featureFlagService->listCidsOn());
    }

    public function testASignedInUserIsNoGuest(): void
    {
        $this->createFlag('a');
        $this->featureFlagService->switchFlag('a', 'gasten', true, 'rick');
        $this->signIn('klant@example.com');

        $this->assertFalse($this->featureFlagService->isOn('a'));
        $this->assertSame([], $this->featureFlagService->listCidsOn());
    }

    public function testAUserInTwoAudiencesHasAFlagOnWhenItIsOnForEither(): void
    {
        $this->createFlag('a');
        $this->createFlag('b');
        $this->createFlag('c');
        $this->featureFlagService->switchFlag('a', 'klanten', true, 'rick');
        $this->featureFlagService->switchFlag('b', 'medewerkers', true, 'rick');
        $this->featureFlagService->switchFlag('c', 'gasten', true, 'rick');
        $this->signIn('rick+beide@example.com');

        $this->assertTrue($this->featureFlagService->isOn('a'));
        $this->assertTrue($this->featureFlagService->isOn('b'));
        $this->assertFalse($this->featureFlagService->isOn('c'));
    }

    public function testAnAudienceTheResolverReturnsButTheConfigLacksIsIgnored(): void
    {
        $this->createFlag('a');
        $this->createFlag('b');
        $this->insertSwitch($this->findFlag('a'), 'onbekend', true);
        $this->featureFlagService->switchFlag('b', 'gasten', true, 'rick');
        $this->signIn('rick+onbekend@example.com');

        $this->assertFalse($this->featureFlagService->isOn('a'));
        $this->assertTrue($this->featureFlagService->isOn('b'));
        $this->assertSame(['b'], $this->featureFlagService->listCidsOn());
    }

    public function testListCidsOnIsSortedByCid(): void
    {
        $this->createFlag('c');
        $this->createFlag('a');
        $this->createFlag('b');
        $this->featureFlagService->switchFlag('c', 'klanten', true, 'rick');
        $this->featureFlagService->switchFlag('a', 'klanten', true, 'rick');
        $this->featureFlagService->switchFlag('b', 'medewerkers', true, 'rick');
        $this->signIn('klant@example.com');

        $this->assertSame(['a', 'c'], $this->featureFlagService->listCidsOn());
    }

    public function testAChangeThroughTheOrmElsewhereIsSeenOnTheNextCheck(): void
    {
        $flag = $this->createFlag('a');
        $this->featureFlagService->switchFlag('a', 'klanten', true, 'rick@example.com');
        $this->assertTrue($this->featureFlagService->isOnFor('a', 'klanten'));

        $switches = $this->featureFlagAudienceRepository->findAllSwitches();
        $switches[0]->switchTo(false, 'lars@example.com');
        $this->entityManager->flush();

        $this->assertFalse($this->featureFlagService->isOnFor('a', 'klanten'));

        $addedSwitch = new FeatureFlagAudience($flag, 'medewerkers', true, 'lars@example.com');
        $this->entityManager->persist($addedSwitch);
        $this->entityManager->flush();

        $this->assertTrue($this->featureFlagService->isOnFor('a', 'medewerkers'));

        $this->entityManager->remove($addedSwitch);
        $this->entityManager->flush();

        $this->assertFalse($this->featureFlagService->isOnFor('a', 'medewerkers'));
    }

    public function testThePendingChangesOfTheCallerAreKeptAndWrittenWithTheSwitch(): void
    {
        $this->createFlag('a');
        $this->featureFlagService->switchFlag('a', 'klanten', true, 'rick@example.com');
        $cronName = 'feature-flag-pending-' . uniqid();
        $cron = new Cron();
        $cron->setName($cronName);
        $cron->setCommand('list');
        $cron->setSchedule('0 9 * * *');
        $this->entityManager->persist($cron);

        $this->featureFlagService->switchFlag('a', 'klanten', false, 'lars@example.com');

        try {
            $this->assertTrue($this->entityManager->isOpen());
            $this->assertTrue($this->entityManager->contains($cron));
            $this->assertNotNull($cron->getId());
            $this->assertSame(
                1,
                (int) $this->connection->fetchOne('SELECT COUNT(1) FROM crons WHERE name = ?', [$cronName]),
            );
            $this->assertFalse($this->featureFlagService->isOnFor('a', 'klanten'));
        } finally {
            $this->connection->executeStatement('DELETE FROM crons WHERE name = ?', [$cronName]);
        }
    }

    public function testASwitchInsideAnOuterTransactionClearsTheCacheOnlyWhenTheProcessEnds(): void
    {
        $this->createFlag('a');
        $this->assertFalse($this->featureFlagService->isOnFor('a', 'klanten'));
        $this->assertSame([], $this->cachedStates());

        $this->connection->beginTransaction();
        $this->featureFlagService->switchFlag('a', 'klanten', true, 'rick@example.com');

        $this->assertSame([], $this->cachedStates());

        $this->connection->commit();

        $this->assertSame([], $this->cachedStates());

        $this->dispatchConsoleTerminate();

        $this->assertNull($this->cachedStates());
        $this->assertTrue($this->featureFlagService->isOnFor('a', 'klanten'));
    }

    public function testASwitchWithoutAnOuterTransactionClearsTheCacheAtOnce(): void
    {
        $this->createFlag('a');
        $this->assertFalse($this->featureFlagService->isOnFor('a', 'klanten'));

        $this->featureFlagService->switchFlag('a', 'klanten', true, 'rick@example.com');

        $this->assertNull($this->cachedStates());
        $this->assertTrue($this->featureFlagService->isOnFor('a', 'klanten'));
    }

    public function testTheCacheListenerRunsAtTheEndOfARequestACommandAndAMessage(): void
    {
        $eventDispatcher = static::getContainer()->get(EventDispatcherInterface::class);

        $eventNames = [
            KernelEvents::TERMINATE,
            ConsoleEvents::TERMINATE,
            WorkerMessageHandledEvent::class,
            WorkerMessageFailedEvent::class,
        ];

        foreach ($eventNames as $eventName) {
            $listenerClasses = [];

            foreach ($eventDispatcher->getListeners($eventName) as $listener) {
                $listenerClasses[] = is_array($listener) && is_object($listener[0]) ? $listener[0]::class : null;
            }

            $this->assertContains(FeatureFlagCacheInvalidationListener::class, $listenerClasses, $eventName);
        }
    }

    /**
     * Records the flushes of this test, as a consumer's onFlush audit would see them; removed again in tearDown.
     */
    private function recordChanges(): FeatureFlagChangeRecorder
    {
        $outsideConnection = DriverManager::getConnection($this->connection->getParams());
        $this->recorder = new FeatureFlagChangeRecorder($outsideConnection);
        $this->entityManager->getEventManager()->addEventListener([Events::onFlush], $this->recorder);

        return $this->recorder;
    }

    private function createFlag(string $cid): FeatureFlag
    {
        $flag = new FeatureFlag();
        $flag->setCid($cid);
        $flag->setName('Flag ' . $cid);

        $this->entityManager->persist($flag);
        $this->entityManager->flush();

        return $flag;
    }

    private function findFlag(string $cid): FeatureFlag
    {
        $flag = $this->entityManager->getRepository(FeatureFlag::class)->findOneBy(['cid' => $cid]);
        $this->assertNotNull($flag);

        return $flag;
    }

    private function insertSwitch(FeatureFlag $flag, string $audience, bool $on): void
    {
        $this->connection->insert('feature_flag_audiences', [
            'feature_flag_id' => $flag->getId(),
            'audience' => $audience,
            'is_on' => (int) $on,
            'updated_at' => '2026-10-19 12:00:00',
            'updated_by' => 'rick@example.com',
        ]);
    }

    private function countSwitchRows(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(1) FROM feature_flag_audiences');
    }

    private function signIn(string $identifier): void
    {
        $user = new InMemoryUser($identifier, null, ['ROLE_USER']);
        static::getContainer()->get(TokenStorageInterface::class)->setToken(
            new UsernamePasswordToken($user, 'api', $user->getRoles()),
        );
    }

    /**
     * @return array<string, array<string, bool>>|null The states in the cache pool, or null when none are cached.
     */
    private function cachedStates(): ?array
    {
        $item = static::getContainer()->get('cache.app')->getItem(self::CACHE_KEY);
        $this->assertInstanceOf(CacheItemPoolInterface::class, static::getContainer()->get('cache.app'));

        return $item->isHit() ? $item->get() : null;
    }

    private function dispatchConsoleTerminate(): void
    {
        static::getContainer()->get(EventDispatcherInterface::class)->dispatch(
            new ConsoleTerminateEvent(new Command('test'), new ArrayInput([]), new NullOutput(), Command::SUCCESS),
            ConsoleEvents::TERMINATE,
        );
    }
}
