<?php

declare(strict_types=1);

namespace App\Messaging\Handler;

use App\Domain\Service\OrderRepository;
use App\Messaging\Message\OrderCreatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async_notifications')]
class SendOrderConfirmationHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private OrderRepository $orderRepository
    ) {}

    public function __invoke(OrderCreatedMessage $message): void
    {
        $this->logger->info('[NOTIFICATIONS] Sending order confirmation email', [
            'orderId' => $message->getOrderId(),
            'email' => $message->getCustomerEmail(),
            'total' => $message->getTotal(),
        ]);

        // Simulate sending email (SMTP, SendGrid, etc.)
        sleep(2);

        $this->logger->info('[NOTIFICATIONS] Confirmation email sent', [
            'orderId' => $message->getOrderId(),
        ]);

        // Update order status
        $order = $this->orderRepository->find($message->getOrderId());
        if ($order !== null) {
            $order->markAsProcessed();
            $this->orderRepository->save($order);
            $this->logger->info('[NOTIFICATIONS] Order status updated to processed', [
                'orderId' => $message->getOrderId(),
            ]);
        }
    }
}