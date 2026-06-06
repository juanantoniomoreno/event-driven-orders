<?php

declare(strict_types=1);

namespace App\Messaging\Handler;

use App\Domain\Service\OrderRepositoryInterface;
use App\Messaging\Message\OrderCreatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Template Method for order event handlers.
 *
 * Each concrete handler only needs to define four things:
 *  - its name (used for idempotency tracking and log prefix)
 *  - how long its simulated work takes
 *  - what to log before and after the work
 *
 * The base class guarantees that idempotency, saving, and Mercure
 * publishing are done consistently for every handler.
 */
abstract class AbstractOrderHandler
{
    public function __construct(
        protected LoggerInterface $logger,
        protected OrderRepositoryInterface $orderRepository,
        protected HubInterface $hub,
    ) {}

    final public function __invoke(OrderCreatedMessage $message): void
    {
        $order = $this->orderRepository->find($message->getOrderId());
        if ($order === null) {
            return;
        }

        // Idempotency guard (Phase 8.1): skip if already processed
        $name = $this->getHandlerName();
        if ($order->isProcessedBy($name)) {
            $this->logger->info(
                sprintf('[%s] Already processed — skipping', strtoupper($name)),
                ['orderId' => $message->getOrderId()],
            );
            return;
        }

        // Hook: child defines what to log before work
        $this->onBeforeWork($message);

        // Simulated work (child defines the duration)
        $clock = new Clock();
        $clock->sleep($this->getSleepSeconds());

        // Hook: child defines what to log after work
        $this->onAfterWork($message);

        // Record processing (idempotent — will not add duplicates)
        $order->markProcessedBy($name);
        $this->orderRepository->save($order);
        $this->logger->info(
            sprintf('[%s] Order status updated to processed', strtoupper($name)),
            ['orderId' => $message->getOrderId()],
        );

        // Publish status update to Mercure
        $update = new Update(
            topics: "/orders/{$order->getId()}/status",
            data: json_encode([
                'orderId' => $order->getId(),
                'status' => $order->getStatus(),
                'processedBy' => $name,
            ]),
        );
        $this->hub->publish($update);
        $this->logger->info(
            sprintf('[%s] Published status update to Mercure', strtoupper($name)),
            ['orderId' => $message->getOrderId()],
        );
    }

    /** Unique handler identifier, e.g. 'notifications', 'inventory', 'analytics'. */
    abstract protected function getHandlerName(): string;

    /** Simulated work duration in seconds. */
    abstract protected function getSleepSeconds(): int;

    /** Log what the handler is about to do. */
    abstract protected function onBeforeWork(OrderCreatedMessage $message): void;

    /** Log what the handler just finished doing. */
    abstract protected function onAfterWork(OrderCreatedMessage $message): void;
}
