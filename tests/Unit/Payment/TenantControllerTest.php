<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tests\Unit\Payment;

use VanDerSangen\ProjectTemplateBundle\Tenant\Controller\TenantController;
use VanDerSangen\ProjectTemplateBundle\Tenant\Entity\Tenant;
use VanDerSangen\ProjectTemplateBundle\Tenant\Repository\TenantRepository;
use VanDerSangen\ProjectTemplateBundle\Tenant\Service\TenantService;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantControllerTest extends TestCase
{
    private TenantService&MockObject $tenantService;
    private TenantRepository&MockObject $tenantRepository;
    private TenantController $controller;
    private User $user;

    protected function setUp(): void
    {
        $this->tenantService = $this->createMock(TenantService::class);
        $this->tenantRepository = $this->createMock(TenantRepository::class);
        
        $this->controller = new class ($this->tenantService, $this->tenantRepository) extends TenantController {
            private ?User $mockUser = null;
            
            public function setMockUser(?User $user): void
            {
                $this->mockUser = $user;
            }
            
            protected function getUser(): ?User
            {
                return $this->mockUser;
            }
        };

        $this->user = $this->createUser(1, 'test@example.com');
        $this->controller->setMockUser($this->user);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $this->controller->setContainer($container);
    }

    private function createUser(int $id, string $email): User
    {
        $user = new User();
        $user->setEmail($email)->setPassword('hashed')->setFirstName('Test')->setLastName('User');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);
        return $user;
    }

    private function createTenant(int $id, int $ownerUserId): Tenant
    {
        $tenant = new Tenant();
        $tenant->setName('Test Tenant')->setOwnerUserId($ownerUserId);
        $ref = new \ReflectionProperty(Tenant::class, 'id');
        $ref->setValue($tenant, $id);
        return $tenant;
    }

    // ==================== create ====================

    public function testCreateTenantSuccess(): void
    {
        $this->tenantRepository->method('findOneBy')->willReturn(null);
        
        $tenant = $this->createTenant(1, 1);
        $this->tenantService->expects($this->once())
            ->method('createTenant')
            ->with('My Company', 1, 'My BV', 'NL123', 'test@example.com')
            ->willReturn($tenant);

        $request = new Request([], [], [], [], [], [], json_encode([
            'name' => 'My Company',
            'companyName' => 'My BV',
            'vatNumber' => 'NL123',
        ]));

        $response = $this->controller->create($request);

        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test Tenant', $data['name']);
    }

    public function testCreateTenantWithoutName(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([]));
        $response = $this->controller->create($request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Name is required', $data['error']);
    }

    public function testCreateTenantWhenUserAlreadyHasTenant(): void
    {
        $existingTenant = $this->createTenant(1, 1);
        $this->tenantRepository->method('findOneBy')
            ->with(['ownerUserId' => 1])
            ->willReturn($existingTenant);

        $request = new Request([], [], [], [], [], [], json_encode(['name' => 'New Tenant']));
        $response = $this->controller->create($request);

        $this->assertEquals(Response::HTTP_CONFLICT, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('User already has a tenant', $data['error']);
    }

    public function testCreateTenantWithoutAuth(): void
    {
        $this->controller->setMockUser(null);
        
        $request = new Request([], [], [], [], [], [], json_encode(['name' => 'Test']));
        $response = $this->controller->create($request);

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    // ==================== current ====================

    public function testCurrentTenantSuccess(): void
    {
        $tenant = $this->createTenant(1, 1);
        $this->tenantRepository->method('findOneBy')
            ->with(['ownerUserId' => 1])
            ->willReturn($tenant);

        $response = $this->controller->current();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test Tenant', $data['name']);
    }

    public function testCurrentTenantNotFound(): void
    {
        $this->tenantRepository->method('findOneBy')->willReturn(null);

        $response = $this->controller->current();

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('No tenant found', $data['error']);
    }

    // ==================== get ====================

    public function testGetTenantSuccess(): void
    {
        $tenant = $this->createTenant(1, 1);
        $this->tenantRepository->method('find')->with(1)->willReturn($tenant);

        $response = $this->controller->get(1);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testGetTenantNotFound(): void
    {
        $this->tenantRepository->method('find')->willReturn(null);

        $response = $this->controller->get(999);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testGetTenantForbiddenForNonOwner(): void
    {
        $tenant = $this->createTenant(1, 999);
        $this->tenantRepository->method('find')->willReturn($tenant);

        $response = $this->controller->get(1);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    // ==================== updateBilling ====================

    public function testUpdateBillingSuccess(): void
    {
        $tenant = $this->createTenant(1, 1);
        $this->tenantRepository->method('find')->with(1)->willReturn($tenant);
        
        $updatedTenant = $this->createTenant(1, 1);
        $updatedTenant->setCompanyName('Updated BV');
        $this->tenantService->method('updateBillingInfo')->willReturn($updatedTenant);

        $request = new Request([], [], [], [], [], [], json_encode([
            'companyName' => 'Updated BV',
            'billingCity' => 'Amsterdam',
        ]));

        $response = $this->controller->updateBilling(1, $request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated BV', $data['companyName']);
    }

    public function testUpdateBillingNotFound(): void
    {
        $this->tenantRepository->method('find')->willReturn(null);

        $request = new Request([], [], [], [], [], [], json_encode([]));
        $response = $this->controller->updateBilling(999, $request);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testUpdateBillingForbiddenForNonOwner(): void
    {
        $tenant = $this->createTenant(1, 999);
        $this->tenantRepository->method('find')->willReturn($tenant);

        $request = new Request([], [], [], [], [], [], json_encode([]));
        $response = $this->controller->updateBilling(1, $request);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
}
