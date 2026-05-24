<?php

declare(strict_types=1);

namespace App\Messaging\Handler;

use App\Messaging\Message\OrderCreatedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class OrderCreatedHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(OrderCreatedMessage $message): void
    {
        $this->logger->info('Processing order', [
            'orderId' => $message->getOrderId(),
            'email' => $message->getCustomerEmail(),
            'total' => $message->getTotal(),
        ]);

        // Simulate async processing
        sleep(1);

        $this->logger->info('Order processed', [
            'orderId' => $message->getOrderId(),
        ]);
    }
}
