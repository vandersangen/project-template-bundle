<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\FeatureFlag\Service;

use BackedEnum;
use LogicException;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagAudienceInterface;
use VanDerSangen\ProjectTemplateBundle\FeatureFlag\FeatureFlagInterface;

/**
 * The flags and audiences of the project, read from the enums named in project_template.feature_flags.
 *
 * Without that config the project has no flags and no audiences: every check is then off and every list empty.
 */
final readonly class FeatureFlagRegistry
{
    /** @var list<FeatureFlagInterface> */
    private array $flags;

    /** @var list<FeatureFlagAudienceInterface> */
    private array $audiences;

    /**
     * @param string|null $flagEnumClass     The enum of the project's flags, or null without flags.
     * @param string|null $audienceEnumClass The enum of the project's audiences, or null without audiences.
     *
     * @throws LogicException When the configuration is invalid (see validateConfiguration()).
     */
    public function __construct(?string $flagEnumClass, ?string $audienceEnumClass)
    {
        self::validateConfiguration($flagEnumClass, $audienceEnumClass);

        $flags = [];

        foreach (self::casesOf($flagEnumClass) as $flag) {
            if ($flag instanceof FeatureFlagInterface) {
                $flags[] = $flag;
            }
        }

        $audiences = [];

        foreach (self::casesOf($audienceEnumClass) as $audience) {
            if ($audience instanceof FeatureFlagAudienceInterface) {
                $audiences[] = $audience;
            }
        }

        $this->flags = $flags;
        $this->audiences = $audiences;
    }

    /**
     * Checks the configured enums; ProjectTemplateExtension calls it while the container is built, so a wrong
     * configuration fails the build (cache:clear, a deploy) and not every request. Nothing configured, nothing checked.
     *
     * @param string|null $flagEnumClass     The enum of the project's flags, or null without flags.
     * @param string|null $audienceEnumClass The enum of the project's audiences, or null without audiences.
     *
     * @throws LogicException When a configured class is no backed enum implementing its interface, or two of its
     *                        cases share a key.
     */
    public static function validateConfiguration(?string $flagEnumClass, ?string $audienceEnumClass): void
    {
        self::validateEnumClass($flagEnumClass, 'flags', FeatureFlagInterface::class);
        self::validateEnumClass($audienceEnumClass, 'audiences', FeatureFlagAudienceInterface::class);
    }

    /**
     * @return list<FeatureFlagInterface>
     */
    public function flags(): array
    {
        return $this->flags;
    }

    /**
     * @return list<FeatureFlagAudienceInterface>
     */
    public function audiences(): array
    {
        return $this->audiences;
    }

    /**
     * @param string $key The stored key of the flag.
     *
     * @return FeatureFlagInterface|null The flag, or null when the project has no flag with that key.
     */
    public function findFlag(string $key): ?FeatureFlagInterface
    {
        foreach ($this->flags as $flag) {
            if ($flag->key() === $key) {
                return $flag;
            }
        }

        return null;
    }

    /**
     * @param string $key The stored key of the audience.
     *
     * @return FeatureFlagAudienceInterface|null The audience, or null when the project has no audience with that key.
     */
    public function findAudience(string $key): ?FeatureFlagAudienceInterface
    {
        foreach ($this->audiences as $audience) {
            if ($audience->key() === $key) {
                return $audience;
            }
        }

        return null;
    }

    /**
     * @param string|null $enumClass The configured class, or null when nothing is configured.
     * @param string      $option    The name of the config option, for the message.
     * @param string      $interface The interface the enum must implement.
     *
     * @throws LogicException When the class is no backed enum implementing the interface, or two cases share a key.
     */
    private static function validateEnumClass(?string $enumClass, string $option, string $interface): void
    {
        if ($enumClass === null) {
            return;
        }

        if (!enum_exists($enumClass) || !is_subclass_of($enumClass, BackedEnum::class)
            || !is_subclass_of($enumClass, $interface)
        ) {
            throw new LogicException(sprintf(
                'project_template.feature_flags.%s names "%s", which must be a backed enum implementing %s.',
                $option,
                $enumClass,
                $interface,
            ));
        }

        $keys = [];

        foreach ($enumClass::cases() as $case) {
            $key = $case instanceof FeatureFlagInterface || $case instanceof FeatureFlagAudienceInterface
                ? $case->key()
                : '';

            if (isset($keys[$key])) {
                throw new LogicException(sprintf(
                    'project_template.feature_flags.%s names "%s", in which more than one case has the key "%s".',
                    $option,
                    $enumClass,
                    $key,
                ));
            }

            $keys[$key] = true;
        }
    }

    /**
     * @param string|null $enumClass A validated enum class, or null when nothing is configured.
     *
     * @return list<BackedEnum>
     */
    private static function casesOf(?string $enumClass): array
    {
        if ($enumClass === null || !is_subclass_of($enumClass, BackedEnum::class)) {
            return [];
        }

        return $enumClass::cases();
    }
}
