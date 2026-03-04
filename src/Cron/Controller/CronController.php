<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Cron\Controller;

use InvalidArgumentException;
use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;
use VanDerSangen\ProjectTemplateBundle\Cron\Service\CronService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class CronController extends AbstractController
{
    public function __construct(
        private readonly CronService $cronService,
    ) {
    }

    #[Route('/super-admin/api/crons', name: 'cron_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $crons = $this->cronService->findAll();
        return $this->json(array_map(static fn (Cron $c) => $c->toArray(), $crons));
    }

    #[Route('/super-admin/api/crons/{id}', name: 'cron_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $cron = $this->cronService->findById($id);
        if (!$cron instanceof Cron) {
            return $this->json(['error' => 'Cron not found'], Response::HTTP_NOT_FOUND);
        }
        return $this->json($cron->toArray());
    }

    #[Route('/super-admin/api/crons', name: 'cron_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->validateCreateData($data);
        $cron = $this->cronService->create($data);
        return $this->json($cron->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/super-admin/api/crons/{id}', name: 'cron_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $cron = $this->cronService->findById($id);
        if (!$cron instanceof Cron) {
            return $this->json(['error' => 'Cron not found'], Response::HTTP_NOT_FOUND);
        }
        $data = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->validateCreateData($data);
        $cron = $this->cronService->update($cron, $data);
        return $this->json($cron->toArray());
    }

    #[Route('/super-admin/api/crons/{id}', name: 'cron_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $cron = $this->cronService->findById($id);
        if (!$cron instanceof Cron) {
            return $this->json(['error' => 'Cron not found'], Response::HTTP_NOT_FOUND);
        }
        $this->cronService->delete($cron);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array $data
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function validateCreateData(array $data): void
    {
        if (empty($data['name']) || !\is_string($data['name'])) {
            throw new InvalidArgumentException('name is required and must be a string');
        }
        if (empty($data['command']) || !\is_string($data['command'])) {
            throw new InvalidArgumentException('command is required and must be a string');
        }
        if (empty($data['schedule']) || !\is_string($data['schedule'])) {
            throw new InvalidArgumentException('schedule is required and must be a string');
        }
    }
}
