<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Shopify\Controller;

use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use VanDerSangen\ProjectTemplateBundle\Shopify\Exception\ShopifyApiException;
use VanDerSangen\ProjectTemplateBundle\Shopify\Service\ShopifyConnectionService;
use VanDerSangen\ProjectTemplateBundle\Tenant\Entity\Tenant;
use VanDerSangen\ProjectTemplateBundle\Tenant\Repository\TenantRepository;
use VanDerSangen\ProjectTemplateBundle\User\Entity\User;

/**
 * Manages the tenant's Shopify connection via custom app credentials
 * (Admin API access token) — no OAuth / App Store app required.
 */
class ShopifyConnectionController extends AbstractController
{
    public function __construct(
        private readonly ShopifyConnectionService $connectionService,
        private readonly TenantRepository $tenantRepository,
    ) {
    }

    #[Route('/api/shopify/connection', name: 'shopify_connection_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $tenant = $this->resolveTenant();
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $connection = $this->connectionService->getForTenant($tenant->getId());
        if ($connection === null) {
            return $this->json(['error' => 'No Shopify connection found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($connection->toArray());
    }

    #[Route('/api/shopify/connection', name: 'shopify_connection_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant();
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $data = json_decode($request->getContent(), true);

        $shopDomain = (string) ($data['shopDomain'] ?? '');
        $accessToken = (string) ($data['accessToken'] ?? '');

        if ($shopDomain === '' || $accessToken === '') {
            return $this->json(
                ['error' => 'shopDomain and accessToken are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $connection = $this->connectionService->connect(
                tenantId: $tenant->getId(),
                shopDomain: $shopDomain,
                accessToken: $accessToken,
                apiKey: $data['apiKey'] ?? null,
                apiSecret: $data['apiSecret'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (ShopifyApiException $exception) {
            return $this->json(
                ['error' => $exception->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return $this->json($connection->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/api/shopify/connection/verify', name: 'shopify_connection_verify', methods: ['POST'])]
    public function verify(): JsonResponse
    {
        $tenant = $this->resolveTenant();
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $connection = $this->connectionService->getForTenant($tenant->getId());
        if ($connection === null) {
            return $this->json(['error' => 'No Shopify connection found'], Response::HTTP_NOT_FOUND);
        }

        $connection = $this->connectionService->verify($connection);

        return $this->json($connection->toArray());
    }

    #[Route('/api/shopify/connection', name: 'shopify_connection_delete', methods: ['DELETE'])]
    public function delete(): JsonResponse
    {
        $tenant = $this->resolveTenant();
        if ($tenant instanceof JsonResponse) {
            return $tenant;
        }

        $connection = $this->connectionService->getForTenant($tenant->getId());
        if ($connection === null) {
            return $this->json(['error' => 'No Shopify connection found'], Response::HTTP_NOT_FOUND);
        }

        $this->connectionService->disconnect($connection);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function resolveTenant(): Tenant|JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $tenant = $this->tenantRepository->findOneBy(['ownerUserId' => $user->getId()]);
        if ($tenant === null) {
            return $this->json(
                ['error' => 'No tenant found. Create a tenant first.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        return $tenant;
    }
}
