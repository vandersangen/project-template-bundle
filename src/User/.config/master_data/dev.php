<?php

declare(strict_types=1);

use VanDerSangen\ProjectTemplateBundle\User\Entity\User;
use VanDerSangen\ProjectTemplateBundle\User\Enum\UserRole;

/**
 * User Module - Master Data Configuration (Development)
 *
 * This configuration is ONLY loaded in the 'dev' environment.
 * Define test users and sample data for development.
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
return [
    'users' => [
        // Test user 1
        [
            'class' => User::class,
            'cid' => 'test-user-1',  // Configuration ID for referencing
            'email' => 'test1@example.com',
            'password' => 'Test123!',
            'firstName' => 'Test',
            'lastName' => 'User One',
            'roles' => [UserRole::USER->value],
        ],

        // Test user 2
        [
            'class' => User::class,
            'cid' => 'test-user-2',
            'email' => 'test2@example.com',
            'password' => 'Test123!',
            'firstName' => 'Test',
            'lastName' => 'User Two',
            'roles' => [UserRole::USER->value],
        ],

        // Developer user
        [
            'class' => User::class,
            'cid' => 'developer-user',
            'email' => 'developer@example.com',
            'password' => 'Dev123!',
            'firstName' => 'Developer',
            'lastName' => 'Account',
            'roles' => [UserRole::ADMIN->value, UserRole::USER->value],
        ],
    ],
];
