<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class FlashController extends AbstractController
{
    #[Route('/clear-flash', name: 'api_clear_flash', methods: ['POST'])]
    public function clearFlash(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $flashBag = $session->getFlashBag();
        $flashBag->clear();

        return new JsonResponse(['success' => true]);
    }
}
