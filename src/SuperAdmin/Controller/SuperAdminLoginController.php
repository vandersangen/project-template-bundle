<?php

declare(strict_types=1);

namespace VanDerSangen\ProjectTemplateBundle\SuperAdmin\Controller;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SuperAdminLoginController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationUtils $authenticationUtils
    ) {
    }

    #[Route('/super-admin', name: 'super_admin_index', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('cron_admin_index');
        }
        return $this->redirectToRoute('super_admin_login');
    }

    #[Route('/super-admin/login', name: 'super_admin_login', methods: ['GET', 'POST'])]
    public function login(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('cron_admin_index');
        }
        $error = $this->authenticationUtils->getLastAuthenticationError();
        $lastUsername = $this->authenticationUtils->getLastUsername();
        return $this->render('@ProjectTemplateBundle/super_admin/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/super-admin/logout', name: 'super_admin_logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        throw new LogicException(
            'This method can be blank - it will be intercepted by the logout key on your firewall.'
        );
    }
}
