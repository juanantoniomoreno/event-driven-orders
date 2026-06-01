<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messaging\Message;

use App\Messaging\Message\OrderCreatedMessage;
use PHPUnit\Framework\TestCase;

class OrderCreatedMessageTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $message = new OrderCreatedMessage(
            'order-123',
            'customer@example.com',
            199.99,
            ['item-1', 'item-2']
        );

        $this->assertSame('order-123', $message->getOrderId());
        $this->assertSame('customer@example.com', $message->getCustomerEmail());
        $this->assertSame(199.99, $message->getTotal());
        $this->assertSame(['item-1', 'item-2'], $message->getItems());
    }

    public function testMessageIsImmutable(): void
    {
        $message = new OrderCreatedMessage('id', 'e@m.com', 0.0, []);

        // Verify there are no setter methods — this is a DTO
        $reflection = new \ReflectionClass($message);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods(\ReflectionMethod::IS_PUBLIC));

        $setters = array_filter($methods, fn($name) => str_starts_with($name, 'set'));
        $this->assertEmpty($setters, 'OrderCreatedMessage should have no public setters');
    }

    public function testEmptyItems(): void
    {
        $message = new OrderCreatedMessage('order-empty', 'e@m.com', 5.0, []);

        $this->assertSame([], $message->getItems());
        $this->assertSame(5.0, $message->getTotal());
    }
}
