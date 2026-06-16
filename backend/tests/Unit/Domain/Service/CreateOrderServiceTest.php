<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Service;

use App\Dto\CreateOrderRequest;
use App\Dto\MoneyInput;
use App\Domain\Entity\Order;
use App\Domain\Service\CreateOrderService;
use App\Domain\Service\OrderRepositoryInterface;
use App\Domain\ValueObject\MoneyEmbeddable;
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

        $request = new CreateOrderRequest(
            customerEmail: 'customer@test.com',
            items: ['sku-a', 'sku-b'],
            total: new MoneyInput(9999, 'USD'),
        );

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
                    && $message->getTotal()->getAmount() === 9999
                    && $message->getTotal()->getCurrency() === 'USD'
                    && $message->getItems() === ['sku-a', 'sku-b'];
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $order = $service->execute($request);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame('customer@test.com', $order->getCustomerEmail());
        $this->assertSame(['sku-a', 'sku-b'], $order->getItems());
        $this->assertSame(9999, $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
        $this->assertSame('pending', $order->getStatus());
    }

    public function testExecuteReturnsOrderWithGeneratedId(): void
    {
        $repository = $this->createMock(OrderRepositoryInterface::class);
        $eventBus = $this->createMock(MessageBusInterface::class);

        $repository->expects($this->once())->method('save');
        $eventBus->expects($this->once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $service = new CreateOrderService($repository, $eventBus);
        $request = new CreateOrderRequest(
            customerEmail: 'test@test.com',
            items: ['item'],
            total: new MoneyInput(1000, 'USD'),
        );
        $order = $service->execute($request);

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
        $request = new CreateOrderRequest(
            customerEmail: 'id-test@test.com',
            items: ['item'],
            total: new MoneyInput(1000, 'USD'),
        );
        $service->execute($request);
    }
}