<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\Cron\Controller;

use InvalidArgumentException;
use VanDerSangen\ProjectTemplateBundle\Cron\Entity\Cron;
use VanDerSangen\ProjectTemplateBundle\Cron\Service\CronService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class CronAdminController extends AbstractController
{
    public function __construct(
        private readonly CronService $cronService,
    ) {
    }

    #[Route('/super-admin/crons', name: 'cron_admin_index', methods: ['GET'])]
    public function index(): Response
    {
        $crons = $this->cronService->findAll();
        return $this->render('@ProjectTemplateBundle/cron_admin/index.html.twig', ['crons' => $crons]);
    }

    #[Route('/super-admin/crons/new', name: 'cron_admin_new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('@ProjectTemplateBundle/cron_admin/form.html.twig', [
            'action' => $this->generateUrl('cron_admin_create'),
            'form' => ['name' => '', 'command' => '', 'schedule' => '', 'timezone' => 'UTC', 'enabled' => true],
        ]);
    }

    #[Route('/super-admin/crons', name: 'cron_admin_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $form = $this->getFormData($request);
        try {
            $this->validateFormData($form);
            $this->cronService->create($this->normalizeFormData($form));
            return $this->redirectToRoute('cron_admin_index');
        } catch (\Throwable $e) {
            return $this->render('@ProjectTemplateBundle/cron_admin/form.html.twig', [
                'action' => $this->generateUrl('cron_admin_create'),
                'form' => $form,
                'error' => $e->getMessage(),
            ]);
        }
    }

    #[Route('/super-admin/crons/{id}/edit', name: 'cron_admin_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(int $id): Response
    {
        $cron = $this->cronService->findById($id);
        if (!$cron instanceof Cron) {
            throw $this->createNotFoundException('Cron not found');
        }
        $form = [
            'name' => $cron->getName(),
            'command' => $cron->getCommand(),
            'commandArguments' => $cron->getCommandArguments(),
            'schedule' => $cron->getSchedule(),
            'timezone' => $cron->getTimezone() ?? 'UTC',
            'enabled' => $cron->isEnabled(),
        ];
        return $this->render('@ProjectTemplateBundle/cron_admin/form.html.twig', [
            'action' => $this->generateUrl('cron_admin_update', ['id' => $id]),
            'form' => $form,
            'cron' => $cron,
        ]);
    }

    #[Route('/super-admin/crons/{id}', name: 'cron_admin_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        $cron = $this->cronService->findById($id);
        if (!$cron instanceof Cron) {
            throw $this->createNotFoundException('Cron not found');
        }
        $form = $this->getFormData($request);
        try {
            $this->validateFormData($form);
            $this->cronService->update($cron, $this->normalizeFormData($form));
            return $this->redirectToRoute('cron_admin_index');
        } catch (\Throwable $e) {
            return $this->render('@ProjectTemplateBundle/cron_admin/form.html.twig', [
                'action' => $this->generateUrl('cron_admin_update', ['id' => $id]),
                'form' => $form,
                'cron' => $cron,
                'error' => $e->getMessage(),
            ]);
        }
    }

    #[Route(
        '/super-admin/crons/{id}/delete',
        name: 'cron_admin_delete',
        requirements: ['id' => '\d+'],
        methods: ['GET']
        )
    ]
    public function deleteConfirm(int $id): Response
    {
        $cron = $this->cronService->findById($id);
        if (!$cron instanceof Cron) {
            throw $this->createNotFoundException('Cron not found');
        }
        return $this->render('@ProjectTemplateBundle/cron_admin/delete.html.twig', ['cron' => $cron]);
    }

    #[Route(
        '/super-admin/crons/{id}/delete',
        name: 'cron_admin_delete_confirm',
        requirements: ['id' => '\d+'],
        methods: ['POST']
        )
    ]
    public function delete(int $id): Response
    {
        $cron = $this->cronService->findById($id);
        if (!$cron instanceof Cron) {
            throw $this->createNotFoundException('Cron not found');
        }

        $this->cronService->delete($cron);
        return $this->redirectToRoute('cron_admin_index');
    }

    /**
     * @return array<string, mixed>
     */
    private function getFormData(Request $request): array
    {
        return [
            'name' => $request->request->getString('name'),
            'command' => $request->request->getString('command'),
            'commandArguments' => $request->request->get('commandArguments'),
            'schedule' => $request->request->getString('schedule'),
            'timezone' => $request->request->getString('timezone') ?: 'UTC',
            'enabled' => $request->request->getBoolean('enabled'),
        ];
    }

    /**
     * @param array $form
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function validateFormData(array $form): void
    {
        if (trim((string) $form['name']) === '') {
            throw new InvalidArgumentException('Naam is verplicht.');
        }
        if (trim((string) $form['command']) === '') {
            throw new InvalidArgumentException('Commando is verplicht.');
        }
        if (trim((string) $form['schedule']) === '') {
            throw new InvalidArgumentException('Cron-expressie is verplicht.');
        }
        $args = $form['commandArguments'];
        if ($args !== null && $args !== '') {
            if (is_string($args)) {
                json_decode($args, true, 512, \JSON_THROW_ON_ERROR);
            }
        }
    }

    /**
     * @param array $form
     *
     * @return array
     *
     * @throws \JsonException
     */
    private function normalizeFormData(array $form): array
    {
        $args = $form['commandArguments'];
        if (is_string($args) && trim($args) !== '') {
            $args = json_decode($args, true, 512, \JSON_THROW_ON_ERROR);
        } elseif ($args === null || $args === '') {
            $args = null;
        }
        return [
            'name' => trim((string) $form['name']),
            'command' => trim((string) $form['command']),
            'commandArguments' => is_array($args) ? $args : null,
            'schedule' => trim((string) $form['schedule']),
            'enabled' => (bool) $form['enabled'],
            'timezone' => trim((string) $form['timezone']) ?: 'UTC',
        ];
    }
}
