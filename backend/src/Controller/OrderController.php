<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Service\CreateOrderService;
use App\Domain\Service\OrderRepositoryInterface;
use App\Dto\CreateOrderRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OrderController
{
    public function __construct(
        private CreateOrderService $createService,
        private OrderRepositoryInterface $repository,
        private ValidatorInterface $validator
    ) {}

    public function create(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $data = json_decode($raw, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(
                ['errors' => ['_body' => ['Invalid JSON body']]],
                Response::HTTP_BAD_REQUEST
            );
        }

        $dto = new CreateOrderRequest(
            $data['customerEmail'] ?? null,
            $data['items'] ?? null,
            $data['total'] ?? null,
        );

        $violations = $this->validator->validate($dto);

        if (count($violations) > 0) {
            return new JsonResponse(
                ['errors' => $this->buildErrorResponse($violations)],
                Response::HTTP_BAD_REQUEST
            );
        }

        $order = $this->createService->execute($dto);

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

    /**
     * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
     * @return array<string, list<string>>
     */
    private function buildErrorResponse($violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();
            $message = $violation->getMessage();
            $errors[$propertyPath][] = $message;
        }
        return $errors;
    }
}
