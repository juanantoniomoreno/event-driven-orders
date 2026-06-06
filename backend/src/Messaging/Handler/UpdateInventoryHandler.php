<?php

declare(strict_types=1);

namespace App\Messaging\Handler;

use App\Messaging\Message\OrderCreatedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async_inventory')]
class UpdateInventoryHandler extends AbstractOrderHandler
{
    protected function getHandlerName(): string
    {
        return 'inventory';
    }

    protected function getSleepSeconds(): int
    {
        return 1;
    }

    protected function onBeforeWork(OrderCreatedMessage $message): void
    {
        $this->logger->info('[INVENTORY] Updating stock for order', [
            'orderId' => $message->getOrderId(),
            'items' => $message->getItems(),
        ]);
    }

    protected function onAfterWork(OrderCreatedMessage $message): void
    {
        $this->logger->info('[INVENTORY] Stock updated', [
            'orderId' => $message->getOrderId(),
        ]);
    }
}
