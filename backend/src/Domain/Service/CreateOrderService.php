<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Dto\CreateOrderRequest;
use App\Domain\Entity\Order;
use App\Domain\ValueObject\MoneyEmbeddable;
use App\Messaging\Message\OrderCreatedMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateOrderService
{
    public function __construct(
        private OrderRepositoryInterface $repository,
        private MessageBusInterface $eventBus
    ) {}

    public function execute(CreateOrderRequest $request): Order
    {
        $total = MoneyEmbeddable::ofUSD($request->total->amount);

        $order = new Order(
            $request->customerEmail,
            $request->items,
            $total,
        );
        $this->repository->save($order);

        $this->eventBus->dispatch(new OrderCreatedMessage(
            $order->getId(),
            $order->getCustomerEmail(),
            $order->getTotal(),
            $order->getItems()
        ));

        return $order;
    }
}