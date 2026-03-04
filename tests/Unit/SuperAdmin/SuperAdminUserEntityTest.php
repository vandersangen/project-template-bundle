<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\SuperAdmin;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use VanDerSangen\ProjectTemplateBundle\SuperAdmin\Entity\SuperAdminUser;

class SuperAdminUserEntityTest extends TestCase
{
    private SuperAdminUser $user;

    protected function setUp(): void
    {
        $this->user = new SuperAdminUser();
    }

    public function testNewUserHasNullId(): void
    {
        $this->assertNull($this->user->getId());
    }

    public function testNewUserHasCreatedAt(): void
    {
        $this->assertInstanceOf(DateTimeImmutable::class, $this->user->getCreatedAt());
    }

    public function testSetAndGetUsername(): void
    {
        $this->user->setUsername('admin');
        $this->assertSame('admin', $this->user->getUsername());
    }

    public function testSetAndGetPassword(): void
    {
        $this->user->setPassword('hashed_password');
        $this->assertSame('hashed_password', $this->user->getPassword());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $at = new DateTimeImmutable('2025-01-01 00:00:00');
        $this->user->setCreatedAt($at);
        $this->assertSame($at, $this->user->getCreatedAt());
    }

    public function testGetUserIdentifierReturnsUsername(): void
    {
        $this->user->setUsername('superadmin');
        $this->assertSame('superadmin', $this->user->getUserIdentifier());
    }

    public function testRolesAlwaysContainRoleSuperAdmin(): void
    {
        $this->assertContains('ROLE_SUPER_ADMIN', $this->user->getRoles());
    }

    public function testEraseCredentialsDoesNothing(): void
    {
        $this->user->setPassword('secret');
        $this->user->eraseCredentials();
        $this->assertSame('secret', $this->user->getPassword());
    }

    public function testSetterReturnsSelf(): void
    {
        $this->assertSame($this->user, $this->user->setUsername('x'));
        $this->assertSame($this->user, $this->user->setPassword('y'));
        $this->assertSame($this->user, $this->user->setCreatedAt(new DateTimeImmutable()));
    }
}
