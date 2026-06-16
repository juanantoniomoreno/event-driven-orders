<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\Order;
use App\Domain\ValueObject\MoneyEmbeddable;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testConstructorCreatesOrderWithMoneyEmbeddable(): void
    {
        $total = MoneyEmbeddable::ofUSD(89999);
        $order = new Order('test@example.com', ['item1'], $total);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $order->getId());
        $this->assertSame('test@example.com', $order->getCustomerEmail());
        $this->assertSame(['item1'], $order->getItems());
        $this->assertInstanceOf(MoneyEmbeddable::class, $order->getTotal());
        $this->assertSame(89999, $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
        $this->assertSame('pending', $order->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->getCreatedAt());
    }

    public function testIdIsRandomHex16(): void
    {
        $order1 = new Order('a@b.com', [], MoneyEmbeddable::ofUSD(100));
        $order2 = new Order('a@b.com', [], MoneyEmbeddable::ofUSD(100));

        $this->assertNotSame($order1->getId(), $order2->getId());
        $this->assertSame(16, strlen($order1->getId()));
        $this->assertSame(16, strlen($order2->getId()));
    }

    public function testMarkAsProcessedChangesStatus(): void
    {
        $order = new Order('test@example.com', [], MoneyEmbeddable::ofUSD(100));
        $this->assertSame('pending', $order->getStatus());

        $order->markAsProcessed();
        $this->assertSame('processed', $order->getStatus());
    }

    public function testMarkAsProcessedIsIdempotent(): void
    {
        $order = new Order('test@example.com', [], MoneyEmbeddable::ofUSD(100));
        $order->markAsProcessed();
        $order->markAsProcessed();

        $this->assertSame('processed', $order->getStatus());
    }

    public function testReconstructRestoresOrderWithMoney(): void
    {
        $createdAt = new \DateTimeImmutable('2025-06-01T12:00:00+00:00');
        $total = MoneyEmbeddable::ofUSD(15050);

        $order = Order::reconstruct(
            'abc123def4567890',
            'restored@example.com',
            ['item-a', 'item-b'],
            $total,
            'shipped',
            $createdAt
        );

        $this->assertSame('abc123def4567890', $order->getId());
        $this->assertSame('restored@example.com', $order->getCustomerEmail());
        $this->assertSame(['item-a', 'item-b'], $order->getItems());
        $this->assertSame(15050, $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
        $this->assertSame('shipped', $order->getStatus());
        $this->assertSame($createdAt, $order->getCreatedAt());
    }

    public function testToArrayReturnsAllFieldsWithNestedMoney(): void
    {
        $total = MoneyEmbeddable::ofUSD(2500);
        $order = new Order('array@test.com', ['sku-1'], $total);
        $array = $order->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('customerEmail', $array);
        $this->assertArrayHasKey('items', $array);
        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('processedBy', $array);
        $this->assertArrayHasKey('createdAt', $array);

        $this->assertSame($order->getId(), $array['id']);
        $this->assertSame('array@test.com', $array['customerEmail']);
        $this->assertSame(['sku-1'], $array['items']);
        $this->assertSame(['amount' => 2500, 'currency' => 'USD'], $array['total']);
        $this->assertSame('pending', $array['status']);
        $this->assertSame([], $array['processedBy']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/',
            $array['createdAt']
        );
    }

    public function testGetProcessedByEmptyByDefault(): void
    {
        $order = new Order('test@example.com', [], MoneyEmbeddable::ofUSD(100));

        $this->assertSame([], $order->getProcessedBy());
    }

    public function testIsProcessedByReturnsFalseWhenNotProcessed(): void
    {
        $order = new Order('test@example.com', [], MoneyEmbeddable::ofUSD(100));

        $this->assertFalse($order->isProcessedBy('notifications'));
        $this->assertFalse($order->isProcessedBy('inventory'));
        $this->assertFalse($order->isProcessedBy('analytics'));
    }

    public function testMarkProcessedByTracksHandler(): void
    {
        $order = new Order('test@example.com', [], MoneyEmbeddable::ofUSD(100));

        $order->markProcessedBy('notifications');

        $this->assertTrue($order->isProcessedBy('notifications'));
        $this->assertSame(['notifications'], $order->getProcessedBy());
        $this->assertSame('processed', $order->getStatus());
    }

    public function testMarkProcessedByIsIdempotent(): void
    {
        $order = new Order('test@example.com', [], MoneyEmbeddable::ofUSD(100));

        $order->markProcessedBy('notifications');
        $order->markProcessedBy('notifications');
        $order->markProcessedBy('notifications');

        $this->assertSame(['notifications'], $order->getProcessedBy());
        $this->assertTrue($order->isProcessedBy('notifications'));
    }

    public function testMarkProcessedByMultipleHandlers(): void
    {
        $order = new Order('test@example.com', [], MoneyEmbeddable::ofUSD(100));

        $order->markProcessedBy('notifications');
        $order->markProcessedBy('inventory');
        $order->markProcessedBy('analytics');

        $this->assertSame(
            ['notifications', 'inventory', 'analytics'],
            $order->getProcessedBy()
        );
        $this->assertTrue($order->isProcessedBy('notifications'));
        $this->assertTrue($order->isProcessedBy('inventory'));
        $this->assertTrue($order->isProcessedBy('analytics'));
    }

    public function testReconstructWithProcessedBy(): void
    {
        $createdAt = new \DateTimeImmutable();
        $processedBy = ['notifications', 'inventory'];

        $order = Order::reconstruct(
            id: 'rec1234567890abcd',
            customerEmail: 'rec@example.com',
            items: ['x', 'y'],
            total: MoneyEmbeddable::ofUSD(5000),
            status: 'processed',
            createdAt: $createdAt,
            processedBy: $processedBy,
        );

        $this->assertSame('rec1234567890abcd', $order->getId());
        $this->assertSame('processed', $order->getStatus());
        $this->assertSame(['notifications', 'inventory'], $order->getProcessedBy());
        $this->assertTrue($order->isProcessedBy('notifications'));
        $this->assertTrue($order->isProcessedBy('inventory'));
        $this->assertFalse($order->isProcessedBy('analytics'));
    }

    public function testReconstructDefaultsToEmptyProcessedBy(): void
    {
        $order = Order::reconstruct(
            id: 'def1234567890abcd',
            customerEmail: 'def@example.com',
            items: ['z'],
            total: MoneyEmbeddable::ofUSD(1000),
            status: 'pending',
            createdAt: new \DateTimeImmutable(),
        );

        $this->assertSame([], $order->getProcessedBy());
    }

    public function testGetTotalReturnsMoneyEmbeddable(): void
    {
        $total = MoneyEmbeddable::ofUSD(4250);
        $order = new Order('money@test.com', ['item'], $total);

        $this->assertInstanceOf(MoneyEmbeddable::class, $order->getTotal());
        $this->assertSame(4250, $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
    }

    public function testOrderWithMinimalAmount(): void
    {
        $order = new Order('minimal@test.com', [], MoneyEmbeddable::ofUSD(1));

        $this->assertSame(1, $order->getTotal()->getAmount());
        $this->assertSame('pending', $order->getStatus());
    }
}