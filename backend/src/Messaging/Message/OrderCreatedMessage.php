<?php

declare(strict_types=1);

namespace App\Messaging\Message;

class OrderCreatedMessage
{
    public function __construct(
        private string $orderId,
        private string $customerEmail,
        private float $total,
        private array $items
    ) {}

    public function getOrderId(): string { return $this->orderId; }
    public function getCustomerEmail(): string { return $this->customerEmail; }
    public function getTotal(): float { return $this->total; }
    public function getItems(): array { return $this->items; }
}
