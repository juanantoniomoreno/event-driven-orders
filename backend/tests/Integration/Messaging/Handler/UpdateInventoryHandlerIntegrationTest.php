<?php

declare(strict_types=1);

namespace App\Tests\Integration\Messaging\Handler;

use App\Domain\Entity\Order;
use App\Domain\Service\DoctrineOrderRepository;
use App\Domain\ValueObject\MoneyEmbeddable;
use App\Messaging\Handler\UpdateInventoryHandler;
use App\Messaging\Message\OrderCreatedMessage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class UpdateInventoryHandlerIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineOrderRepository $repository;
    private HubInterface&MockObject $hub;
    private LoggerInterface&MockObject $logger;
    private UpdateInventoryHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->repository = new DoctrineOrderRepository($this->entityManager);
        $this->hub = $this->createMock(HubInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new UpdateInventoryHandler(
            $this->logger,
            $this->repository,
            $this->hub,
        );
    }

    public function testHandlerMarksOrderAsProcessed(): void
    {
        // ARRANGE: create a pending order in the real DB
        $order = Order::reconstruct(
            id: 'invtryOrder00001',
            customerEmail: 'warehouse@example.com',
            items: ['sku-123', 'sku-456', 'sku-789'],
            total: MoneyEmbeddable::ofUSD(27550),
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
                return $data['orderId'] === 'invtryOrder00001'
                    && $data['status'] === 'processed'
                    && $data['processedBy'] === 'inventory';
            }));

        $message = new OrderCreatedMessage('invtryOrder00001', 'warehouse@example.com', MoneyEmbeddable::ofUSD(27550), ['sku-123', 'sku-456', 'sku-789']);

        // ACT
        $this->handler->__invoke($message);

        // ASSERT: order is now processed in the DB
        $this->entityManager->clear();
        $updated = $this->repository->find('invtryOrder00001');

        $this->assertNotNull($updated, 'Order should still exist in the DB');
        $this->assertSame('processed', $updated->getStatus(), 'Handler should have called markAsProcessed');
        $this->assertSame(['inventory'], $updated->getProcessedBy(), 'Handler should have recorded itself in processedBy');
    }

    public function testHandlerSkipsWhenAlreadyProcessed(): void
    {
        // ARRANGE: create an order already processed by inventory
        $order = Order::reconstruct(
            id: 'skipInvtry00001',
            customerEmail: 'done@example.com',
            items: ['bolt'],
            total: MoneyEmbeddable::ofUSD(150),
            status: 'processed',
            createdAt: new \DateTimeImmutable(),
            processedBy: ['notifications', 'inventory'],
        );
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Mercure should NOT be called
        $this->hub->expects($this->never())->method('publish');
        $this->logger->expects($this->any())->method('info');

        $message = new OrderCreatedMessage('skipInvtry00001', 'done@example.com', MoneyEmbeddable::ofUSD(150), ['bolt']);

        // ACT
        $this->handler->__invoke($message);

        // ASSERT: processedBy unchanged (still 2 handlers, not 3 with duplicate)
        $this->entityManager->clear();
        $updated = $this->repository->find('skipInvtry00001');

        $this->assertNotNull($updated);
        $this->assertSame(
            ['notifications', 'inventory'],
            $updated->getProcessedBy(),
            'processedBy should not have changed — inventory handler should have skipped'
        );
    }

    public function testHandlerDoesNotCrashWhenOrderNotFound(): void
    {
        // ARRANGE: NO order in DB
        $this->hub->expects($this->never())->method('publish');
        $this->logger->expects($this->any())->method('info');

        $message = new OrderCreatedMessage('ghostInvtry00001', 'nobody@example.com', MoneyEmbeddable::ofUSD(100), []);

        // ACT
        $this->handler->__invoke($message);

        // ASSERT: nothing crashed
        $this->addToAssertionCount(1);
    }
}