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

#[AsMessageHandler(fromTransport: 'async_notifications')]
class SendOrderConfirmationHandler
{
    private const string NAME = 'notifications';

    public function __construct(
        private LoggerInterface $logger,
        private OrderRepositoryInterface $orderRepository,
        private HubInterface $hub
    ) {}

    public function __invoke(OrderCreatedMessage $message): void
    {
        $order = $this->orderRepository->find($message->getOrderId());
        if ($order === null) {
            return;
        }

        // Idempotency guard: skip if this handler already processed this order.
        // Prevents duplicate side effects on message retry (Phase 8.1).
        if ($order->isProcessedBy(self::NAME)) {
            $this->logger->info('[NOTIFICATIONS] Already processed — skipping', [
                'orderId' => $message->getOrderId(),
            ]);
            return;
        }

        $this->logger->info('[NOTIFICATIONS] Sending order confirmation email', [
            'orderId' => $message->getOrderId(),
            'email' => $message->getCustomerEmail(),
            'total' => $message->getTotal(),
        ]);

        // Simulate sending email (SMTP, SendGrid, etc.)
        $clock = new Clock();
        $clock->sleep(2);

        $this->logger->info('[NOTIFICATIONS] Confirmation email sent', [
            'orderId' => $message->getOrderId(),
        ]);

        // Record that this handler processed the order (idempotent)
        $order->markProcessedBy(self::NAME);
        $this->orderRepository->save($order);
        $this->logger->info('[NOTIFICATIONS] Order status updated to processed', [
            'orderId' => $message->getOrderId(),
        ]);

        // Publish status update to Mercure
        $update = new Update(
            topics: "/orders/{$order->getId()}/status",
            data: json_encode([
                'orderId' => $order->getId(),
                'status' => $order->getStatus(),
                'processedBy' => self::NAME,
            ])
        );
        $this->hub->publish($update);
        $this->logger->info('[NOTIFICATIONS] Published status update to Mercure', [
            'orderId' => $message->getOrderId(),
        ]);
    }
}
