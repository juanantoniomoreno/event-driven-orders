<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Service;

use App\Domain\Entity\Order;
use App\Domain\Service\CreateOrderService;
use App\Domain\Service\OrderRepositoryInterface;
use App\Messaging\Message\OrderCreatedMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CreateOrderServiceTest extends TestCase
{
    public function testExecuteCreatesOrderAndDispatchesMessage(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $eventBus = $this->createMock(MessageBusInterface::class);

        $service = new CreateOrderService($repository, $eventBus);

        // Expect repository.save() to be called once with any Order
        $repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Order::class));

        // Expect eventBus.dispatch() to be called once with an OrderCreatedMessage
        $eventBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof OrderCreatedMessage
                    && $message->getCustomerEmail() === 'customer@test.com'
                    && $message->getTotal() === 99.99
                    && $message->getItems() === ['sku-a', 'sku-b'];
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $order = $service->execute('customer@test.com', ['sku-a', 'sku-b'], 99.99);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame('customer@test.com', $order->getCustomerEmail());
        $this->assertSame(['sku-a', 'sku-b'], $order->getItems());
        $this->assertSame(99.99, $order->getTotal());
        $this->assertSame('pending', $order->getStatus());
    }

    public function testExecuteReturnsOrderWithGeneratedId(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $eventBus = $this->createMock(MessageBusInterface::class);

        $repository->expects($this->once())->method('save');
        $eventBus->expects($this->once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $service = new CreateOrderService($repository, $eventBus);
        $order = $service->execute('test@test.com', [], 0.0);

        $this->assertNotEmpty($order->getId());
        $this->assertSame(16, strlen($order->getId()));
    }

    public function testExecuteDispatchesMessageWithCorrectOrderId(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $eventBus = $this->createMock(MessageBusInterface::class);

        $repository->expects($this->once())->method('save');
        $eventBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (OrderCreatedMessage $message) {
                return strlen($message->getOrderId()) === 16;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $service = new CreateOrderService($repository, $eventBus);
        $service->execute('id-test@test.com', ['item'], 10.0);
    }
}
