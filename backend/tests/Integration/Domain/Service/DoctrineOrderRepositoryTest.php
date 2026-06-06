<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain\Service;

use App\Domain\Entity\Order;
use App\Domain\Service\DoctrineOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineOrderRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineOrderRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->repository = new DoctrineOrderRepository($this->entityManager);
    }

    public function testSavePersistsOrderToDatabase(): void
    {
        // ARRANGE
        $order = Order::reconstruct(
            id: '550e8400e29b41d4',
            customerEmail: 'integration@test.com',
            items: ['laptop', 'mouse'],
            total: 1499.98,
            status: 'pending',
            createdAt: new \DateTimeImmutable('2026-06-01'),
        );

        // ACT
        $this->repository->save($order);

        // ASSERT
        $this->entityManager->clear(); // force fresh fetch from DB

        $found = $this->repository->find('550e8400e29b41d4');
        $this->assertNotNull($found, 'Order should be persisted and retrievable by ID');
        $this->assertSame('550e8400e29b41d4', $found->getId());
        $this->assertSame('integration@test.com', $found->getCustomerEmail());
        $this->assertSame(['laptop', 'mouse'], $found->getItems());
        $this->assertSame(1499.98, $found->getTotal());
        $this->assertSame('pending', $found->getStatus());
    }

    public function testFindReturnsNullForNonExistentId(): void
    {
        $found = $this->repository->find('nonexistent-id');
        $this->assertNull($found);
    }

    public function testFindAllReturnsOrdersOrderedByCreatedAtDesc(): void
    {
        // ARRANGE
        $older = Order::reconstruct(
            id: 'older00000000001',
            customerEmail: 'older@test.com',
            items: ['item-a'],
            total: 10.0,
            status: 'processed',
            createdAt: new \DateTimeImmutable('2026-01-01'),
        );
        $newer = Order::reconstruct(
            id: 'newer00000000001',
            customerEmail: 'newer@test.com',
            items: ['item-b'],
            total: 20.0,
            status: 'pending',
            createdAt: new \DateTimeImmutable('2026-06-01'),
        );

        $this->entityManager->persist($older);
        $this->entityManager->persist($newer);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // ACT
        $all = $this->repository->findAll();

        // ASSERT — newest first (filter to our IDs only, in case DAMA didn't isolate)
        $testOrderIds = ['older00000000001', 'newer00000000001'];
        $filtered = array_values(array_filter(
            $all,
            fn (Order $o): bool => \in_array($o->getId(), $testOrderIds, true)
        ));
        $this->assertCount(2, $filtered, 'Both test orders should be present');
        $this->assertSame('newer00000000001', $filtered[0]->getId(), 'Newest order should come first');
        $this->assertSame('older00000000001', $filtered[1]->getId(), 'Older order should come second');
    }

    public function testSaveUpdatesExistingOrder(): void
    {
        // ARRANGE
        $order = Order::reconstruct(
            id: 'update-me-id',
            customerEmail: 'before@test.com',
            items: ['old-item'],
            total: 42.0,
            status: 'pending',
            createdAt: new \DateTimeImmutable(),
        );
        $this->repository->save($order);
        $this->entityManager->clear();

        // Reload, mutate, save again
        $found = $this->repository->find('update-me-id');
        $this->assertNotNull($found);
        $found->markAsProcessed();
        $this->repository->save($found);
        $this->entityManager->clear();

        // ACT
        $updated = $this->repository->find('update-me-id');

        // ASSERT
        $this->assertNotNull($updated);
        $this->assertSame('processed', $updated->getStatus(), 'Status should be updated after markAsProcessed');
    }
}
