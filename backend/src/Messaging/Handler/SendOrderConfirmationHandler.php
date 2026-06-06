<?php

declare(strict_types=1);

namespace App\Messaging\Handler;

use App\Messaging\Message\OrderCreatedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async_notifications')]
class SendOrderConfirmationHandler extends AbstractOrderHandler
{
    protected function getHandlerName(): string
    {
        return 'notifications';
    }

    protected function getSleepSeconds(): int
    {
        return 2;
    }

    protected function onBeforeWork(OrderCreatedMessage $message): void
    {
        $this->logger->info('[NOTIFICATIONS] Sending order confirmation email', [
            'orderId' => $message->getOrderId(),
            'email' => $message->getCustomerEmail(),
            'total' => $message->getTotal(),
        ]);
    }

    protected function onAfterWork(OrderCreatedMessage $message): void
    {
        $this->logger->info('[NOTIFICATIONS] Confirmation email sent', [
            'orderId' => $message->getOrderId(),
        ]);
    }
}
