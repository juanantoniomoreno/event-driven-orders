<?php

declare(strict_types=1);

namespace App\Messaging\Message;

use App\Domain\ValueObject\MoneyEmbeddable;

class OrderCreatedMessage
{
    public function __construct(
        private string $orderId,
        private string $customerEmail,
        private MoneyEmbeddable $total,
        private array $items
    ) {}

    public function getOrderId(): string { return $this->orderId; }
    public function getCustomerEmail(): string { return $this->customerEmail; }
    public function getTotal(): MoneyEmbeddable { return $this->total; }
    public function getItems(): array { return $this->items; }
}