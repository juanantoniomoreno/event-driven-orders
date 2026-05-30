<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Service\CreateOrderService;
use App\Domain\Service\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController
{
    public function __construct(
        private CreateOrderService $createService,
        private OrderRepositoryInterface $repository
    ) {}

    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $order = $this->createService->execute(
            $data['customerEmail'] ?? '',
            $data['items'] ?? [],
            $data['total'] ?? 0.0
        );

        return new JsonResponse($order->toArray(), Response::HTTP_CREATED);
    }

    public function list(): JsonResponse
    {
        $orders = $this->repository->findAll();
        return new JsonResponse(array_map(fn($o) => $o->toArray(), $orders));
    }

    public function get(string $id): JsonResponse
    {
        $order = $this->repository->find($id);
        if (!$order) {
            return new JsonResponse(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
        }
        return new JsonResponse($order->toArray());
    }
}
