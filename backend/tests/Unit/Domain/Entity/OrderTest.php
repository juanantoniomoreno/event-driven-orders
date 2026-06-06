<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\Order;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testConstructorCreatesOrderWithDefaults(): void
    {
        $order = new Order('test@example.com', ['item1'], 99.99);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $order->getId());
        $this->assertSame('test@example.com', $order->getCustomerEmail());
        $this->assertSame(['item1'], $order->getItems());
        $this->assertSame(99.99, $order->getTotal());
        $this->assertSame('pending', $order->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->getCreatedAt());
    }

    public function testIdIsRandomHex16(): void
    {
        $order1 = new Order('a@b.com', [], 0.0);
        $order2 = new Order('a@b.com', [], 0.0);

        $this->assertNotSame($order1->getId(), $order2->getId());
        $this->assertSame(16, strlen($order1->getId()));
        $this->assertSame(16, strlen($order2->getId()));
    }

    public function testMarkAsProcessedChangesStatus(): void
    {
        $order = new Order('test@example.com', [], 0.0);
        $this->assertSame('pending', $order->getStatus());

        $order->markAsProcessed();
        $this->assertSame('processed', $order->getStatus());
    }

    public function testMarkAsProcessedIsIdempotent(): void
    {
        $order = new Order('test@example.com', [], 0.0);
        $order->markAsProcessed();
        $order->markAsProcessed();

        $this->assertSame('processed', $order->getStatus());
    }

    public function testReconstructRestoresOrder(): void
    {
        $createdAt = new \DateTimeImmutable('2025-06-01T12:00:00+00:00');

        $order = Order::reconstruct(
            'abc123def4567890',
            'restored@example.com',
            ['item-a', 'item-b'],
            150.50,
            'shipped',
            $createdAt
        );

        $this->assertSame('abc123def4567890', $order->getId());
        $this->assertSame('restored@example.com', $order->getCustomerEmail());
        $this->assertSame(['item-a', 'item-b'], $order->getItems());
        $this->assertSame(150.50, $order->getTotal());
        $this->assertSame('shipped', $order->getStatus());
        $this->assertSame($createdAt, $order->getCreatedAt());
    }

    public function testToArrayReturnsAllFields(): void
    {
        $order = new Order('array@test.com', ['sku-1'], 25.00);
        $array = $order->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('customerEmail', $array);
        $this->assertArrayHasKey('items', $array);
        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('createdAt', $array);

        $this->assertSame($order->getId(), $array['id']);
        $this->assertSame('array@test.com', $array['customerEmail']);
        $this->assertSame(['sku-1'], $array['items']);
        $this->assertSame(25.00, $array['total']);
        $this->assertSame('pending', $array['status']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/',
            $array['createdAt']
        );
    }

    public function testEmptyItemsAndZeroTotal(): void
    {
        $order = new Order('minimal@test.com', [], 0.0);

        $this->assertSame([], $order->getItems());
        $this->assertSame(0.0, $order->getTotal());
        $this->assertSame('pending', $order->getStatus());
    }

    public function testEmailWithSpecialCharacters(): void
    {
        $order = new Order('user+tag@sub.example.co.uk', [], 10.0);

        $this->assertSame('user+tag@sub.example.co.uk', $order->getCustomerEmail());
    }
}
