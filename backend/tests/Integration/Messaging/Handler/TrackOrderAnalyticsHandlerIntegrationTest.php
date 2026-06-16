<?php

declare(strict_types=1);

namespace App\Tests\Integration\Messaging\Handler;

use App\Domain\Entity\Order;
use App\Domain\Service\DoctrineOrderRepository;
use App\Domain\ValueObject\MoneyEmbeddable;
use App\Messaging\Handler\TrackOrderAnalyticsHandler;
use App\Messaging\Message\OrderCreatedMessage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class TrackOrderAnalyticsHandlerIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineOrderRepository $repository;
    private HubInterface&MockObject $hub;
    private LoggerInterface&MockObject $logger;
    private TrackOrderAnalyticsHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->repository = new DoctrineOrderRepository($this->entityManager);
        $this->hub = $this->createMock(HubInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new TrackOrderAnalyticsHandler(
            $this->logger,
            $this->repository,
            $this->hub,
        );
    }

    public function testHandlerMarksOrderAsProcessed(): void
    {
        // ARRANGE: create a pending order in the real DB
        $order = Order::reconstruct(
            id: 'anlytcOrder00001',
            customerEmail: 'analytics@example.com',
            items: ['laptop', 'mouse', 'cable'],
            total: MoneyEmbeddable::ofUSD(89999),
            status: 'pending',
            createdAt: new \DateTimeImmutable(),
        );
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Expect the analytics-specific log format: "89999 USD"
        $this->logger->expects($this->atLeast(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context = []): void {
                if (str_contains($message, 'Tracking order metrics')) {
                    assert($context['total'] === '89999 USD', 'Expected total "89999 USD", got "' . $context['total'] . '"');
                }
            });

        $this->hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                $data = json_decode($update->getData(), true);
                return $data['orderId'] === 'anlytcOrder00001'
                    && $data['status'] === 'processed'
                    && $data['processedBy'] === 'analytics';
            }));

        $message = new OrderCreatedMessage('anlytcOrder00001', 'analytics@example.com', MoneyEmbeddable::ofUSD(89999), ['laptop', 'mouse', 'cable']);

        // ACT
        $this->handler->__invoke($message);

        // ASSERT: order is now processed in the DB
        $this->entityManager->clear();
        $updated = $this->repository->find('anlytcOrder00001');

        $this->assertNotNull($updated, 'Order should still exist in the DB');
        $this->assertSame('processed', $updated->getStatus(), 'Handler should have called markAsProcessed');
        $this->assertSame(['analytics'], $updated->getProcessedBy(), 'Handler should have recorded itself in processedBy');
    }

    public function testHandlerSkipsWhenAlreadyProcessed(): void
    {
        // ARRANGE: create an order already processed by analytics
        $order = Order::reconstruct(
            id: 'skipAnlyt000001',
            customerEmail: 'done@example.com',
            items: ['book'],
            total: MoneyEmbeddable::ofUSD(2500),
            status: 'processed',
            createdAt: new \DateTimeImmutable(),
            processedBy: ['notifications', 'analytics'],
        );
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Mercure should NOT be called
        $this->hub->expects($this->never())->method('publish');
        $this->logger->expects($this->any())->method('info');

        $message = new OrderCreatedMessage('skipAnlyt000001', 'done@example.com', MoneyEmbeddable::ofUSD(2500), ['book']);

        // ACT
        $this->handler->__invoke($message);

        // ASSERT: processedBy unchanged (still 2 handlers, not 3 with duplicate)
        $this->entityManager->clear();
        $updated = $this->repository->find('skipAnlyt000001');

        $this->assertNotNull($updated);
        $this->assertSame(
            ['notifications', 'analytics'],
            $updated->getProcessedBy(),
            'processedBy should not have changed — analytics handler should have skipped'
        );
    }

    public function testHandlerDoesNotCrashWhenOrderNotFound(): void
    {
        // ARRANGE: NO order in DB
        $this->hub->expects($this->never())->method('publish');
        $this->logger->expects($this->any())->method('info');

        $message = new OrderCreatedMessage('ghostAnlyt00001', 'nobody@example.com', MoneyEmbeddable::ofUSD(100), []);

        // ACT
        $this->handler->__invoke($message);

        // ASSERT: nothing crashed
        $this->addToAssertionCount(1);
    }
}
