<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\Api\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('api_health');
    }
}
