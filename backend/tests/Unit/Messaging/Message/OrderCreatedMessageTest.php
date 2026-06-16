<?php

declare(strict_types=1);

namespace App\Tests\Unit\Messaging\Message;

use App\Domain\ValueObject\MoneyEmbeddable;
use App\Messaging\Message\OrderCreatedMessage;
use PHPUnit\Framework\TestCase;

class OrderCreatedMessageTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $total = MoneyEmbeddable::ofUSD(19999);
        $message = new OrderCreatedMessage(
            'order-123',
            'customer@example.com',
            $total,
            ['item-1', 'item-2']
        );

        $this->assertSame('order-123', $message->getOrderId());
        $this->assertSame('customer@example.com', $message->getCustomerEmail());
        $this->assertSame(19999, $message->getTotal()->getAmount());
        $this->assertSame('USD', $message->getTotal()->getCurrency());
        $this->assertSame(['item-1', 'item-2'], $message->getItems());
    }

    public function testMessageIsImmutable(): void
    {
        $total = MoneyEmbeddable::ofUSD(500);
        $message = new OrderCreatedMessage('id', 'e@m.com', $total, []);

        // Verify there are no setter methods — this is a DTO
        $reflection = new \ReflectionClass($message);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods(\ReflectionMethod::IS_PUBLIC));

        $setters = array_filter($methods, fn($name) => str_starts_with($name, 'set'));
        $this->assertEmpty($setters, 'OrderCreatedMessage should have no public setters');
    }

    public function testMoneyEmbeddableRoundTripInMessage(): void
    {
        $total = MoneyEmbeddable::ofUSD(4250);
        $message = new OrderCreatedMessage('order-456', 'user@test.com', $total, ['sku-1']);

        $retrievedTotal = $message->getTotal();
        $this->assertSame(4250, $retrievedTotal->getAmount());
        $this->assertSame('USD', $retrievedTotal->getCurrency());
        $this->assertSame(['amount' => 4250, 'currency' => 'USD'], $retrievedTotal->toArray());
    }
}