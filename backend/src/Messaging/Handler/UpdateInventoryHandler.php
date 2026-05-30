<?php

declare(strict_types=1);

namespace App\Messaging\Handler;

use App\Domain\Service\OrderRepository;
use App\Messaging\Message\OrderCreatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async_inventory')]
class UpdateInventoryHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private OrderRepository $orderRepository
    ) {}

    public function __invoke(OrderCreatedMessage $message): void
    {
        $this->logger->info('[INVENTORY] Updating stock for order', [
            'orderId' => $message->getOrderId(),
            'items' => $message->getItems(),
        ]);

        // Simulate updating stock in database
        sleep(1);

        $this->logger->info('[INVENTORY] Stock updated', [
            'orderId' => $message->getOrderId(),
        ]);

        // Update order status
        $order = $this->orderRepository->find($message->getOrderId());
        if ($order !== null) {
            $order->markAsProcessed();
            $this->orderRepository->save($order);
            $this->logger->info('[INVENTORY] Order status updated to processed', [
                'orderId' => $message->getOrderId(),
            ]);
        }
    }
}