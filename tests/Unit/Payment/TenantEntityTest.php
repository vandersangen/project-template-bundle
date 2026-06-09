<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Payment;

use VanDerSangen\ProjectTemplateBundle\Tenant\Entity\Tenant;
use VanDerSangen\ProjectTemplateBundle\Tenant\Exception\TenantOwnerProtectionException;
use PHPUnit\Framework\TestCase;

class TenantEntityTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $tenant = new Tenant();
        $this->assertNull($tenant->getId());
        $this->assertNull($tenant->getName());
        $this->assertNull($tenant->getCompanyName());
        $this->assertNull($tenant->getVatNumber());
        $this->assertNull($tenant->getBillingEmail());
        $this->assertNull($tenant->getBillingCountry());
        $this->assertNotNull($tenant->getCreatedAt());
        $this->assertNull($tenant->getUpdatedAt());
    }

    public function testSettersAndGetters(): void
    {
        $tenant = new Tenant();
        $tenant->setName('Acme Corp')
            ->setCompanyName('Acme BV')
            ->setVatNumber('NL123456789B01')
            ->setBillingEmail('billing@acme.com')
            ->setBillingStreet('Hoofdstraat')
            ->setBillingHouseNumber('1')
            ->setBillingCity('Amsterdam')
            ->setBillingPostalCode('1234AB')
            ->setBillingCountry('NL')
            ->setOwnerUserId(42);

        $this->assertSame('Acme Corp', $tenant->getName());
        $this->assertSame('Acme BV', $tenant->getCompanyName());
        $this->assertSame('NL123456789B01', $tenant->getVatNumber());
        $this->assertSame('billing@acme.com', $tenant->getBillingEmail());
        $this->assertSame('Hoofdstraat', $tenant->getBillingStreet());
        $this->assertSame('1', $tenant->getBillingHouseNumber());
        $this->assertSame('Amsterdam', $tenant->getBillingCity());
        $this->assertSame('1234AB', $tenant->getBillingPostalCode());
        $this->assertSame('NL', $tenant->getBillingCountry());
        $this->assertSame(42, $tenant->getOwnerUserId());
    }

    public function testToArrayContainsExpectedKeys(): void
    {
        $tenant = new Tenant();
        $tenant->setName('Test')->setOwnerUserId(1);
        $array = $tenant->toArray();

        $expectedKeys = [
            'id',
            'name',
            'companyName',
            'vatNumber',
            'billingEmail',
            'billingStreet',
            'billingHouseNumber',
            'billingCity',
            'billingPostalCode',
            'billingCountry',
            'ownerUserId',
            'createdAt',
            'updatedAt',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array, "Missing key: $key");
        }
    }

    public function testToArrayValues(): void
    {
        $tenant = new Tenant();
        $tenant->setName('My Tenant')
            ->setCompanyName('My BV')
            ->setOwnerUserId(7)
            ->setBillingCountry('NL');

        $array = $tenant->toArray();
        $this->assertSame('My Tenant', $array['name']);
        $this->assertSame('My BV', $array['companyName']);
        $this->assertSame(7, $array['ownerUserId']);
        $this->assertSame('NL', $array['billingCountry']);
        $this->assertNull($array['id']);
    }

    public function testTenantOwnerProtectionExceptionMessage(): void
    {
        $exception = TenantOwnerProtectionException::cannotDeleteOwner(5, 3);
        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertStringContainsString('5', $exception->getMessage());
        $this->assertStringContainsString('3', $exception->getMessage());
        $this->assertStringContainsString('owner', $exception->getMessage());
    }
}
