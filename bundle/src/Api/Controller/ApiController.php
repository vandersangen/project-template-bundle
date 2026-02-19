<?php

declare(strict_types=1);

namespace LarsVanDerSangen\ProjectTemplateBundle\Api\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ApiController extends AbstractController
{
    #[Route('/api/hello', name: 'api_hello', methods: ['GET'])]
    public function hello(): JsonResponse
    {
        return $this->json([
            'message' => 'Hello from Symfony API!',
            'status' => 'success',
            'timestamp' => date('c'),
        ]);
    }
}

