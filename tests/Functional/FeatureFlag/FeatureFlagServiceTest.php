<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Functional\FeatureFlag;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Entity\FeatureFlagSetting;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Repository\FeatureFlagSettingRepository;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagService;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\DuplicateKeyAudience;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\UnconfiguredFeatureFlag;

class FeatureFlagServiceTest extends KernelTestCase
{
    private FeatureFlagService $featureFlagService;
    private FeatureFlagSettingRepository $featureFlagSettingRepository;
    private EntityManagerInterface $entityManager;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->featureFlagService = $container->get(FeatureFlagService::class);
        $this->featureFlagSettingRepository = $container->get(FeatureFlagSettingRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $this->entityManager->getConnection();

        $this->connection->executeStatement('DELETE FROM feature_flags');
        $this->featureFlagService->clearCachedStates();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DELETE FROM feature_flags');
        $this->featureFlagService->clearCachedStates();

        parent::tearDown();
    }

    public function testAFlagNobodySwitchedIsOffForEveryAudience(): void
    {
        foreach (TestFeatureFlag::cases() as $featureFlag) {
            foreach (TestFeatureFlagAudience::cases() as $audience) {
                $this->assertFalse($this->featureFlagService->isOnFor($featureFlag, $audience));
            }

            $this->assertFalse($this->featureFlagService->isOnForBackgroundWork($featureFlag));
        }

        $this->assertSame([], $this->featureFlagService->listFlagsOnFor(TestFeatureFlagAudience::CUSTOMERS));
    }

    public function testSwitchingOnCountsForThatAudienceOnly(): void
    {
        $this->featureFlagService->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::STAFF,
            true,
            'rick@example.com',
        );

        $this->assertTrue(
            $this->featureFlagService->isOnFor(TestFeatureFlag::NEW_DASHBOARD, TestFeatureFlagAudience::STAFF),
        );
        $this->assertFalse(
            $this->featureFlagService->isOnFor(TestFeatureFlag::NEW_DASHBOARD, TestFeatureFlagAudience::CUSTOMERS),
        );
        $this->assertFalse(
            $this->featureFlagService->isOnFor(TestFeatureFlag::NIGHTLY_REPORT, TestFeatureFlagAudience::STAFF),
        );

        $setting = $this->featureFlagSettingRepository->findSettingOf('new_dashboard', 'medewerkers');
        $this->assertNotNull($setting);
        $this->assertSame('new_dashboard', $setting->getFeatureFlagKey());
        $this->assertSame('medewerkers', $setting->getAudienceKey());
        $this->assertTrue($setting->isOn());
        $this->assertSame('rick@example.com', $setting->getUpdatedBy());
    }

    public function testSwitchingOffAgain(): void
    {
        $this->featureFlagService->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::CUSTOMERS,
            true,
            'rick@example.com',
        );
        $this->featureFlagService->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::CUSTOMERS,
            false,
            'lars@example.com',
        );

        $this->assertFalse(
            $this->featureFlagService->isOnFor(TestFeatureFlag::NEW_DASHBOARD, TestFeatureFlagAudience::CUSTOMERS),
        );

        $setting = $this->featureFlagSettingRepository->findSettingOf('new_dashboard', 'klanten');
        $this->assertNotNull($setting);
        $this->assertFalse($setting->isOn());
        $this->assertSame('lars@example.com', $setting->getUpdatedBy());
    }

    public function testBackgroundWorkFollowsTheBackgroundAudienceOfTheFlag(): void
    {
        $this->featureFlagService->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::STAFF,
            true,
            'rick@example.com',
        );
        $this->featureFlagService->switchFlag(
            TestFeatureFlag::NIGHTLY_REPORT,
            TestFeatureFlagAudience::STAFF,
            true,
            'rick@example.com',
        );

        $this->assertFalse($this->featureFlagService->isOnForBackgroundWork(TestFeatureFlag::NEW_DASHBOARD));
        $this->assertTrue($this->featureFlagService->isOnForBackgroundWork(TestFeatureFlag::NIGHTLY_REPORT));
    }

    public function testListsTheFlagsOnForAnAudience(): void
    {
        $this->featureFlagService->switchFlag(
            TestFeatureFlag::NIGHTLY_REPORT,
            TestFeatureFlagAudience::CUSTOMERS,
            true,
            'rick@example.com',
        );
        $this->featureFlagService->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::STAFF,
            true,
            'rick@example.com',
        );

        $this->assertSame(
            [TestFeatureFlag::NIGHTLY_REPORT],
            $this->featureFlagService->listFlagsOnFor(TestFeatureFlagAudience::CUSTOMERS),
        );
        $this->assertSame(
            [TestFeatureFlag::NEW_DASHBOARD],
            $this->featureFlagService->listFlagsOnFor(TestFeatureFlagAudience::STAFF),
        );
    }

    public function testARowOfAFlagOrAudienceTheProjectNoLongerHasIsIgnored(): void
    {
        $this->insertRow('removed_flag', 'klanten');
        $this->insertRow('new_dashboard', 'removed_audience');
        $this->insertRow('nightly_report', 'klanten');

        $this->assertSame(
            [TestFeatureFlag::NIGHTLY_REPORT],
            $this->featureFlagService->listFlagsOnFor(TestFeatureFlagAudience::CUSTOMERS),
        );
        $this->assertFalse(
            $this->featureFlagService->isOnFor(TestFeatureFlag::NEW_DASHBOARD, TestFeatureFlagAudience::CUSTOMERS),
        );
        $this->assertFalse($this->featureFlagService->isOnForBackgroundWork(TestFeatureFlag::NEW_DASHBOARD));

        $settings = $this->featureFlagSettingRepository->findAllSettings();
        $this->assertCount(1, $settings);
        $this->assertSame('nightly_report', $settings[0]->getFeatureFlagKey());
        $this->assertSame('klanten', $settings[0]->getAudienceKey());
    }

    public function testAFlagOrAudienceTheRegistryDoesNotKnowIsOffEvenWithARow(): void
    {
        $this->insertRow('elsewhere', 'klanten');
        $this->insertRow('new_dashboard', 'same');

        $this->assertFalse(
            $this->featureFlagService->isOnFor(UnconfiguredFeatureFlag::ELSEWHERE, TestFeatureFlagAudience::CUSTOMERS),
        );
        $this->assertFalse($this->featureFlagService->isOnForBackgroundWork(UnconfiguredFeatureFlag::ELSEWHERE));
        $this->assertFalse(
            $this->featureFlagService->isOnFor(TestFeatureFlag::NEW_DASHBOARD, DuplicateKeyAudience::FIRST),
        );
    }

    public function testAChangeThroughTheOrmElsewhereIsSeenOnTheNextCheck(): void
    {
        $this->featureFlagService->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::CUSTOMERS,
            true,
            'rick@example.com',
        );
        $this->assertTrue(
            $this->featureFlagService->isOnFor(TestFeatureFlag::NEW_DASHBOARD, TestFeatureFlagAudience::CUSTOMERS),
        );

        $setting = $this->featureFlagSettingRepository->findSettingOf('new_dashboard', 'klanten');
        $this->assertNotNull($setting);
        $setting->switchTo(false, 'lars@example.com');
        $this->entityManager->flush();

        $this->assertFalse(
            $this->featureFlagService->isOnFor(TestFeatureFlag::NEW_DASHBOARD, TestFeatureFlagAudience::CUSTOMERS),
        );

        $addedSetting = new FeatureFlagSetting('nightly_report', 'klanten', true, 'lars@example.com');
        $this->entityManager->persist($addedSetting);
        $this->entityManager->flush();

        $this->assertTrue(
            $this->featureFlagService->isOnFor(TestFeatureFlag::NIGHTLY_REPORT, TestFeatureFlagAudience::CUSTOMERS),
        );

        $this->entityManager->remove($addedSetting);
        $this->entityManager->flush();

        $this->assertFalse(
            $this->featureFlagService->isOnFor(TestFeatureFlag::NIGHTLY_REPORT, TestFeatureFlagAudience::CUSTOMERS),
        );
    }

    public function testSavingTheSameStateTwiceChangesNothing(): void
    {
        $this->featureFlagService->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::CUSTOMERS,
            true,
            'rick@example.com',
        );
        $before = $this->connection->fetchAssociative('SELECT * FROM feature_flags');

        $this->featureFlagService->switchFlag(
            TestFeatureFlag::NEW_DASHBOARD,
            TestFeatureFlagAudience::CUSTOMERS,
            true,
            'lars@example.com',
        );

        $this->assertSame($before, $this->connection->fetchAssociative('SELECT * FROM feature_flags'));
        $this->assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(1) FROM feature_flags'));
        $this->assertTrue(
            $this->featureFlagService->isOnFor(TestFeatureFlag::NEW_DASHBOARD, TestFeatureFlagAudience::CUSTOMERS),
        );
    }

    private function insertRow(string $featureFlagKey, string $audienceKey): void
    {
        $this->connection->insert('feature_flags', [
            'feature_flag' => $featureFlagKey,
            'audience' => $audienceKey,
            'is_on' => 1,
            'updated_at' => '2026-10-19 12:00:00',
            'updated_by' => 'rick@example.com',
        ]);
    }
}
