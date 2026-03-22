<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HealthController
{
    #[Route('/up', name: 'health_check', methods: ['GET'])]
    public function up(): Response
    {
        return new Response('', Response::HTTP_OK);
    }
}
