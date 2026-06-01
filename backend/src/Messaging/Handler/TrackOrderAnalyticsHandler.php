<?php

declare(strict_types=1);

namespace App\Messaging\Handler;

use App\Domain\Service\OrderRepositoryInterface;
use App\Messaging\Message\OrderCreatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async_analytics')]
class TrackOrderAnalyticsHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private OrderRepositoryInterface $orderRepository,
        private HubInterface $hub
    ) {}

    public function __invoke(OrderCreatedMessage $message): void
    {
        $this->logger->info('[ANALYTICS] Tracking order metrics', [
            'orderId' => $message->getOrderId(),
            'total' => $message->getTotal(),
            'itemCount' => count($message->getItems()),
        ]);

        // Simulate saving metrics to external service (Mixpanel, Datadog, etc.)
        Clock::sleep(1);

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

            // Publish status update to Mercure
            $update = new Update(
                topics: "/orders/{$order->getId()}/status",
                data: json_encode([
                    'orderId' => $order->getId(),
                    'status' => $order->getStatus(),
                    'processedBy' => 'analytics',
                ])
            );
            $this->hub->publish($update);
            $this->logger->info('[ANALYTICS] Published status update to Mercure', [
                'orderId' => $message->getOrderId(),
            ]);
        }
    }
}