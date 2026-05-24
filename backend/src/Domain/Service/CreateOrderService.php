<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Order;
use App\Messaging\Message\OrderCreatedMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateOrderService
{
    public function __construct(
        private OrderRepository $repository,
        private MessageBusInterface $eventBus
    ) {}

    public function execute(string $email, array $items, float $total): Order
    {
        $order = new Order($email, $items, $total);
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
