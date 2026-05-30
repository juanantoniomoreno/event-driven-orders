<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function save(Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }

    public function find(string $id): ?Order
    {
        return $this->entityManager->find(Order::class, $id);
    }

    /** @return Order[] */
    public function findAll(): array
    {
        return $this->entityManager
            ->getRepository(Order::class)
            ->findBy([], ['createdAt' => 'DESC']);
    }
}