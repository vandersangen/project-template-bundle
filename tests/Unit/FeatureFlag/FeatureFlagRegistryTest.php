<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag;

use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\FeatureFlagRegistry;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service\SingleAudienceResolver;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\App\FeatureFlag\TestFeatureFlagAudience;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\DuplicateKeyAudience;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\DuplicateKeyFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\EnumWithoutInterface;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\ForeignBackgroundAudienceFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\LongKeyAudience;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\LongKeyFeatureFlag;
use VanDerSangen\ProjectTemplateBundle\Tests\Unit\FeatureFlag\Fixtures\UnitEnumFeatureFlag;

class FeatureFlagRegistryTest extends TestCase
{
    public function testListsTheConfiguredFlagsAndAudiences(): void
    {
        $registry = new FeatureFlagRegistry(TestFeatureFlag::class, TestFeatureFlagAudience::class);

        $this->assertSame(TestFeatureFlag::cases(), $registry->flags());
        $this->assertSame(TestFeatureFlagAudience::cases(), $registry->audiences());
    }

    public function testFindsAFlagAndAnAudienceByKey(): void
    {
        $registry = new FeatureFlagRegistry(TestFeatureFlag::class, TestFeatureFlagAudience::class);

        $this->assertSame(TestFeatureFlag::NIGHTLY_REPORT, $registry->findFlag('nightly_report'));
        $this->assertSame(TestFeatureFlagAudience::STAFF, $registry->findAudience('medewerkers'));
        $this->assertNull($registry->findFlag('removed_flag'));
        $this->assertNull($registry->findAudience('removed_audience'));
    }

    public function testWithoutConfigurationItHasNoFlagsAndNoAudiences(): void
    {
        $registry = new FeatureFlagRegistry(null, null);

        $this->assertSame([], $registry->flags());
        $this->assertSame([], $registry->audiences());
        $this->assertNull($registry->findFlag('new_dashboard'));
        $this->assertNull($registry->findAudience('klanten'));
    }

    public function testRefusesAFlagClassThatIsNoEnum(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(stdClass::class);
        $this->expectExceptionMessage(FeatureFlagInterface::class);

        new FeatureFlagRegistry(stdClass::class, TestFeatureFlagAudience::class);
    }

    public function testRefusesAFlagClassThatDoesNotExist(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('App\\FeatureFlag\\Missing');

        new FeatureFlagRegistry('App\\FeatureFlag\\Missing', TestFeatureFlagAudience::class);
    }

    public function testRefusesAFlagEnumWithoutTheInterface(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(EnumWithoutInterface::class);
        $this->expectExceptionMessage(FeatureFlagInterface::class);

        new FeatureFlagRegistry(EnumWithoutInterface::class, TestFeatureFlagAudience::class);
    }

    public function testRefusesAFlagEnumWithoutBackingValues(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(UnitEnumFeatureFlag::class);

        new FeatureFlagRegistry(UnitEnumFeatureFlag::class, TestFeatureFlagAudience::class);
    }

    public function testRefusesAnAudienceEnumWithoutTheInterface(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(EnumWithoutInterface::class);
        $this->expectExceptionMessage(FeatureFlagAudienceInterface::class);

        new FeatureFlagRegistry(TestFeatureFlag::class, EnumWithoutInterface::class);
    }

    public function testRefusesAFlagEnumGivenAsAudiences(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(FeatureFlagAudienceInterface::class);

        new FeatureFlagRegistry(TestFeatureFlag::class, TestFeatureFlag::class);
    }

    public function testRefusesTwoFlagsWithTheSameKey(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(DuplicateKeyFeatureFlag::class);
        $this->expectExceptionMessage('"same"');

        new FeatureFlagRegistry(DuplicateKeyFeatureFlag::class, TestFeatureFlagAudience::class);
    }

    public function testRefusesTwoAudiencesWithTheSameKey(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(DuplicateKeyAudience::class);
        $this->expectExceptionMessage('"same"');

        new FeatureFlagRegistry(TestFeatureFlag::class, DuplicateKeyAudience::class);
    }

    public function testRefusesFlagsWithoutAudiences(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('project_template.feature_flags.audiences');

        new FeatureFlagRegistry(TestFeatureFlag::class, null);
    }

    public function testAcceptsAudiencesWithoutFlags(): void
    {
        $registry = new FeatureFlagRegistry(null, TestFeatureFlagAudience::class);

        $this->assertSame([], $registry->flags());
        $this->assertSame(TestFeatureFlagAudience::cases(), $registry->audiences());
    }

    public function testRefusesABackgroundAudienceOfAnotherEnum(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(ForeignBackgroundAudienceFeatureFlag::class);
        $this->expectExceptionMessage(TestFeatureFlagAudience::class);

        new FeatureFlagRegistry(ForeignBackgroundAudienceFeatureFlag::class, TestFeatureFlagAudience::class);
    }

    public function testRefusesAFlagKeyLongerThanFiftyCharacters(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('a_flag_key_of_fifty_one_characters_which_is_too_lng');
        $this->expectExceptionMessage('50');

        new FeatureFlagRegistry(LongKeyFeatureFlag::class, TestFeatureFlagAudience::class);
    }

    public function testRefusesAnAudienceKeyLongerThanTwentyCharacters(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('audience_key_too_long');
        $this->expectExceptionMessage('20');

        new FeatureFlagRegistry(TestFeatureFlag::class, LongKeyAudience::class);
    }

    public function testTheDefaultResolverGivesTheFirstConfiguredAudience(): void
    {
        $resolver = new SingleAudienceResolver(
            new FeatureFlagRegistry(TestFeatureFlag::class, TestFeatureFlagAudience::class),
        );

        $this->assertSame(TestFeatureFlagAudience::CUSTOMERS, $resolver->resolveAudienceOfCurrentRequest());
    }

    public function testTheDefaultResolverRefusesOnlyWhenAskedWithoutAudiences(): void
    {
        $resolver = new SingleAudienceResolver(new FeatureFlagRegistry(null, null));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Configure project_template.feature_flags.audiences.');

        $resolver->resolveAudienceOfCurrentRequest();
    }
}
