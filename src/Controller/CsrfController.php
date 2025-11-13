<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CsrfController extends AbstractController
{
    #[Route('/_csrf/{tokenId}', name: 'app_csrf_token', methods: ['GET'])]
    public function token(string $tokenId, Request $request): JsonResponse
    {
        try {
            $tokenManager = $this->container->get('security.csrf.token_manager');
            $token = $tokenManager->getToken($tokenId);
            
            // Force the token to be a real value, not a placeholder
            // by calling getValue() which generates the actual stateless token
            $tokenValue = $token->getValue();
            
        } catch (\Throwable $e) {
            return $this->json(['error' => 'unable_to_generate_token'], 500);
        }

        return $this->json(['token' => $tokenValue]);
    }
}
