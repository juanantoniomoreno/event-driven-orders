<?php

declare(strict_types=1);

namespace App\Tests\Integration\Messaging\Handler;

use App\Domain\Entity\Order;
use App\Domain\Service\DoctrineOrderRepository;
use App\Domain\ValueObject\MoneyEmbeddable;
use App\Messaging\Handler\SendOrderConfirmationHandler;
use App\Messaging\Message\OrderCreatedMessage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class SendOrderConfirmationHandlerIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineOrderRepository $repository;
    private HubInterface&MockObject $hub;
    private LoggerInterface&MockObject $logger;
    private SendOrderConfirmationHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->repository = new DoctrineOrderRepository($this->entityManager);
        $this->hub = $this->createMock(HubInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SendOrderConfirmationHandler(
            $this->logger,
            $this->repository,
            $this->hub,
        );
    }

    public function testHandlerMarksOrderAsProcessed(): void
    {
        // ARRANGE: create a pending order in the real DB
        $order = Order::reconstruct(
            id: 'notifOrder000001',
            customerEmail: 'customer@example.com',
            items: ['keyboard', 'monitor'],
            total: MoneyEmbeddable::ofUSD(34999),
            status: 'pending',
            createdAt: new \DateTimeImmutable(),
        );
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->logger->expects($this->any())->method('info');
        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $data = json_decode($update->getData(), true);
                return $data['orderId'] === 'notifOrder000001'
                    && $data['status'] === 'processed';
            }));

        $message = new OrderCreatedMessage('notifOrder000001', 'customer@example.com', MoneyEmbeddable::ofUSD(34999), ['keyboard', 'monitor']);

        // ACT: invoke the handler directly
        $this->handler->__invoke($message);

        // ASSERT: order is now processed in the DB
        $this->entityManager->clear();
        $updated = $this->repository->find('notifOrder000001');

        $this->assertNotNull($updated, 'Order should still exist in the DB');
        $this->assertSame('processed', $updated->getStatus(), 'Handler should have called markAsProcessed');
        $this->assertSame(['notifications'], $updated->getProcessedBy(), 'Handler should have recorded itself in processedBy');
    }

    public function testHandlerSkipsWhenAlreadyProcessed(): void
    {
        // ARRANGE: create an order already processed by notifications
        $order = Order::reconstruct(
            id: 'skipNotif000001',
            customerEmail: 'already@example.com',
            items: ['widget'],
            total: MoneyEmbeddable::ofUSD(999),
            status: 'processed',
            createdAt: new \DateTimeImmutable(),
            processedBy: ['notifications'],
        );
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Mercure should NOT be called — handler should skip
        $this->hub->expects($this->never())->method('publish');
        $this->logger->expects($this->any())->method('info');

        $message = new OrderCreatedMessage('skipNotif000001', 'already@example.com', MoneyEmbeddable::ofUSD(999), ['widget']);

        // ACT
        $this->handler->__invoke($message);

        // ASSERT: processedBy should still be exactly what it was (no duplicate)
        $this->entityManager->clear();
        $updated = $this->repository->find('skipNotif000001');

        $this->assertNotNull($updated);
        $this->assertSame(
            ['notifications'],
            $updated->getProcessedBy(),
            'processedBy should not have changed — handler should have skipped'
        );
    }

    public function testHandlerDoesNothingWhenOrderNotFound(): void
    {
        // ARRANGE: NO order in DB
        $this->hub->expects($this->never())->method('publish');
        $this->logger->expects($this->any())->method('info');

        $message = new OrderCreatedMessage('nonexistent-order', 'ghost@example.com', MoneyEmbeddable::ofUSD(100), []);

        // ACT: this should NOT throw
        $this->handler->__invoke($message);

        // ASSERT: nothing crashed, Mercure was never called
        $this->addToAssertionCount(1); // explicit: no exception = pass
    }
}