<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;

class MercureJwtController
{
    public function __construct(
        private TokenFactoryInterface $tokenFactory,
    ) {}

    /**
     * Generates a subscriber JWT for the Mercure hub.
     *
     * In development, the token allows subscribing to any order status topic.
     * In production, you should scope this to the authenticated user's orders.
     */
    public function token(): JsonResponse
    {
        $token = $this->tokenFactory->create(
            subscribe: ['/orders/{id}/status'],
        );

        return new JsonResponse(['token' => $token]);
    }
}
