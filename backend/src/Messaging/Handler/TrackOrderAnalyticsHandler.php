<?php

declare(strict_types=1);

namespace App\Messaging\Handler;

use App\Domain\Service\OrderRepository;
use App\Messaging\Message\OrderCreatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async_analytics')]
class TrackOrderAnalyticsHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private OrderRepository $orderRepository
    ) {}

    public function __invoke(OrderCreatedMessage $message): void
    {
        $this->logger->info('[ANALYTICS] Tracking order metrics', [
            'orderId' => $message->getOrderId(),
            'total' => $message->getTotal(),
            'itemCount' => count($message->getItems()),
        ]);

        // Simulate saving metrics to external service (Mixpanel, Datadog, etc.)
        sleep(1);

        $this->logger->info('[ANALYTICS] Metrics recorded', [
            'orderId' => $message->getOrderId(),
        ]);

        // Update order status
        $order = $this->orderRepository->find($message->getOrderId());
        if ($order !== null) {
            $order->markAsProcessed();
            $this->orderRepository->save($order);
            $this->logger->info('[ANALYTICS] Order status updated to processed', [
                'orderId' => $message->getOrderId(),
            ]);
        }
    }
}