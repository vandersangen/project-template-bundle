<?php

declare(strict_types=1);

use LarsVanDerSangen\ProjectTemplateBundle\User\Entity\User;
use LarsVanDerSangen\ProjectTemplateBundle\User\Enum\UserRole;

/**
 * User Module - Master Data Configuration (Default)
 *
 * This configuration is loaded in ALL environments (dev, test, prod).
 * Define users that must always exist in the database.
 *
 * Benefits of PHP configuration:
 * - Type safety with enums and constants
 * - IDE autocompletion
 * - Refactoring support
 * - Syntax validation
 * - Automatic entity hydration via 'class' property
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
return [
    'users' => [
        // Admin user - always required
        [
            'class' => User::class,
            'cid' => 'admin-user',
            'email' => 'admin@example.com',
            'password' => 'Admin123!',  // Will be hashed automatically
            'firstName' => 'Admin',
            'lastName' => 'User',
            'roles' => [UserRole::ADMIN->value, UserRole::USER->value],
        ],

        // System user for automated processes
        [
            'class' => User::class,
            'cid' => 'system-user',
            'email' => 'system@example.com',
            'password' => 'System123!',
            'firstName' => 'System',
            'lastName' => 'Account',
            'roles' => [UserRole::SYSTEM->value, UserRole::USER->value],
        ],
    ],
];

