<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\User\Enum;

/**
 * User roles enum.
 *
 * This enum defines all available user roles in the system.
 * Use this in master data configuration for type safety.
 *
 * Example usage in master_data configuration:
 * 'roles' => [UserRole::ADMIN->value, UserRole::USER->value]
 */
enum UserRole: string
{
    case ADMIN = 'ROLE_ADMIN';
    case USER = 'ROLE_USER';
    case SYSTEM = 'ROLE_SYSTEM';
    case MODERATOR = 'ROLE_MODERATOR';

    /**
     * Get a human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::USER => 'User',
            self::SYSTEM => 'System',
            self::MODERATOR => 'Moderator',
        };
    }

    /**
     * Get all role values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn(self $role) => $role->value, self::cases());
    }
}
