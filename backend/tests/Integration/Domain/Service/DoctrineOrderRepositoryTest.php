<?php

declare(strict_types=1);

namespace App\Tests\Integration\Domain\Service;

use App\Domain\Entity\Order;
use App\Domain\Service\DoctrineOrderRepository;
use App\Domain\ValueObject\MoneyEmbeddable;
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
            total: MoneyEmbeddable::ofUSD(149998),
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
        $this->assertSame(149998, $found->getTotal()->getAmount());
        $this->assertSame('USD', $found->getTotal()->getCurrency());
        $this->assertSame('pending', $found->getStatus());
    }

    public function testMoneyEmbeddablePersistAndLoadRoundTrip(): void
    {
        // ARRANGE — verify BIGINT persistence with real DB
        $order = Order::reconstruct(
            id: 'moneyrt000001tes',
            customerEmail: 'roundtrip@test.com',
            items: ['sku-x'],
            total: MoneyEmbeddable::ofUSD(4250),
            status: 'pending',
            createdAt: new \DateTimeImmutable(),
        );

        // ACT
        $this->repository->save($order);
        $this->entityManager->clear();

        $found = $this->repository->find('moneyrt000001tes');

        // ASSERT — MoneyEmbeddable survives persist/load round-trip
        $this->assertNotNull($found);
        $this->assertSame(4250, $found->getTotal()->getAmount());
        $this->assertSame('USD', $found->getTotal()->getCurrency());
        $this->assertSame(['amount' => 4250, 'currency' => 'USD'], $found->getTotal()->toArray());
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
            total: MoneyEmbeddable::ofUSD(1000),
            status: 'processed',
            createdAt: new \DateTimeImmutable('2026-01-01'),
        );
        $newer = Order::reconstruct(
            id: 'newer00000000001',
            customerEmail: 'newer@test.com',
            items: ['item-b'],
            total: MoneyEmbeddable::ofUSD(2000),
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
            total: MoneyEmbeddable::ofUSD(4200),
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