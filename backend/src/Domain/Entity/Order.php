<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\MoneyEmbeddable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 16)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $customerEmail;

    #[ORM\Column(type: 'json')]
    private array $items;

    #[ORM\Embedded(class: MoneyEmbeddable::class, columnPrefix: false)]
    private MoneyEmbeddable $total;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $processedBy = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $customerEmail,
        array $items,
        MoneyEmbeddable $total
    ) {
        $this->id = bin2hex(random_bytes(8));
        $this->customerEmail = $customerEmail;
        $this->items = $items;
        $this->total = $total;
        $this->status = 'pending';
        $this->processedBy = [];
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function reconstruct(
        string $id,
        string $customerEmail,
        array $items,
        MoneyEmbeddable $total,
        string $status,
        \DateTimeImmutable $createdAt,
        array $processedBy = []
    ): self {
        $order = new self($customerEmail, $items, $total);
        $order->id = $id;
        $order->status = $status;
        $order->createdAt = $createdAt;
        $order->processedBy = $processedBy;
        return $order;
    }

    public function getId(): string { return $this->id; }
    public function getCustomerEmail(): string { return $this->customerEmail; }
    public function getItems(): array { return $this->items; }
    public function getTotal(): MoneyEmbeddable { return $this->total; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getProcessedBy(): array { return $this->processedBy; }

    public function markAsProcessed(): void { $this->status = 'processed'; }

    /**
     * Has this specific handler already processed this order?
     * Idempotency guard: returns true if the handler name is in the processedBy list.
     */
    public function isProcessedBy(string $handlerName): bool
    {
        return \in_array($handlerName, $this->processedBy, true);
    }

    /**
     * Record that a handler has processed this order.
     * Idempotent: does not add duplicates. Also marks the order status as processed.
     */
    public function markProcessedBy(string $handlerName): void
    {
        if (!\in_array($handlerName, $this->processedBy, true)) {
            $this->processedBy[] = $handlerName;
        }
        $this->markAsProcessed();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customerEmail' => $this->customerEmail,
            'items' => $this->items,
            'total' => $this->total->toArray(),
            'status' => $this->status,
            'processedBy' => $this->processedBy,
            'createdAt' => $this->createdAt->format('c'),
        ];
    }
}