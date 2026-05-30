<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Order;

interface OrderRepositoryInterface
{
    public function save(Order $order): void;

    public function find(string $id): ?Order;

    /** @return Order[] */
    public function findAll(): array;
}