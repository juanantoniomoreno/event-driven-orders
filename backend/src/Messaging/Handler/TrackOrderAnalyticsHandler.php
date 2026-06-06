<?php

declare(strict_types=1);

namespace App\Messaging\Handler;

use App\Messaging\Message\OrderCreatedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async_analytics')]
class TrackOrderAnalyticsHandler extends AbstractOrderHandler
{
    protected function getHandlerName(): string
    {
        return 'analytics';
    }

    protected function getSleepSeconds(): int
    {
        return 1;
    }

    protected function onBeforeWork(OrderCreatedMessage $message): void
    {
        $this->logger->info('[ANALYTICS] Tracking order metrics', [
            'orderId' => $message->getOrderId(),
            'total' => $message->getTotal(),
            'itemCount' => count($message->getItems()),
        ]);
    }

    protected function onAfterWork(OrderCreatedMessage $message): void
    {
        $this->logger->info('[ANALYTICS] Metrics recorded', [
            'orderId' => $message->getOrderId(),
        ]);
    }
}
