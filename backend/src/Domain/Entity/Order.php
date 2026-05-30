<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Service\DoctrineOrderRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrineOrderRepository::class)]
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

    #[ORM\Column(type: 'float')]
    private float $total;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $customerEmail,
        array $items,
        float $total
    ) {
        $this->id = bin2hex(random_bytes(8));
        $this->customerEmail = $customerEmail;
        $this->items = $items;
        $this->total = $total;
        $this->status = 'pending';
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function reconstruct(
        string $id,
        string $customerEmail,
        array $items,
        float $total,
        string $status,
        \DateTimeImmutable $createdAt
    ): self {
        $order = new self($customerEmail, $items, $total);
        $order->id = $id;
        $order->status = $status;
        $order->createdAt = $createdAt;
        return $order;
    }

    public function getId(): string { return $this->id; }
    public function getCustomerEmail(): string { return $this->customerEmail; }
    public function getItems(): array { return $this->items; }
    public function getTotal(): float { return $this->total; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function markAsProcessed(): void { $this->status = 'processed'; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customerEmail' => $this->customerEmail,
            'items' => $this->items,
            'total' => $this->total,
            'status' => $this->status,
            'createdAt' => $this->createdAt->format('c'),
        ];
    }
}