<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Tenant\Controller;

use VanDerSangen\ProjectTemplateBundle\Tenant\Repository\TenantRepository;
use VanDerSangen\ProjectTemplateBundle\Tenant\Service\TenantService;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TenantController extends AbstractController
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    #[Route('/api/tenants', name: 'tenant_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $existingTenant = $this->tenantRepository->findOneBy(['ownerUserId' => $user->getId()]);
        if ($existingTenant !== null) {
            return $this->json(['error' => 'User already has a tenant'], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);

        $name = $data['name'] ?? '';
        if (empty($name)) {
            return $this->json(['error' => 'Name is required'], Response::HTTP_BAD_REQUEST);
        }

        $tenant = $this->tenantService->createTenant(
            name: $name,
            ownerUserId: $user->getId(),
            companyName: $data['companyName'] ?? null,
            vatNumber: $data['vatNumber'] ?? null,
            billingEmail: $data['billingEmail'] ?? $user->getEmail(),
        );

        return $this->json($tenant->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/tenants/current', name: 'tenant_current', methods: ['GET'])]
    public function current(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $tenant = $this->tenantRepository->findOneBy(['ownerUserId' => $user->getId()]);
        if ($tenant === null) {
            return $this->json(['error' => 'No tenant found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($tenant->toArray());
    }

    #[Route('/api/tenants/{id}', name: 'tenant_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $tenant = $this->tenantRepository->find($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        if ($tenant->getOwnerUserId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($tenant->toArray());
    }

    #[Route('/api/tenants/{id}/billing', name: 'tenant_update_billing', methods: ['PATCH'])]
    public function updateBilling(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $tenant = $this->tenantRepository->find($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        if ($tenant->getOwnerUserId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        $tenant = $this->tenantService->updateBillingInfo(
            tenant: $tenant,
            companyName: $data['companyName'] ?? null,
            vatNumber: $data['vatNumber'] ?? null,
            billingEmail: $data['billingEmail'] ?? null,
            billingAddressLine1: $data['billingAddressLine1'] ?? null,
            billingAddressLine2: $data['billingAddressLine2'] ?? null,
            billingCity: $data['billingCity'] ?? null,
            billingPostalCode: $data['billingPostalCode'] ?? null,
            billingCountry: $data['billingCountry'] ?? null,
        );

        return $this->json($tenant->toArray());
    }
}
