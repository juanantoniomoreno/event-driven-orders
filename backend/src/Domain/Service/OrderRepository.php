<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Order;

class OrderRepository implements OrderRepositoryInterface
{
    private \PDO $pdo;

    public function __construct()
    {
        $dbPath = dirname(__DIR__, 3) . '/var/orders.db';
        $this->pdo = new \PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->init();
    }

    private function init(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id TEXT PRIMARY KEY,
                customer_email TEXT NOT NULL,
                items TEXT NOT NULL,
                total REAL NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL
            )
        ");
    }

    public function save(Order $order): void
    {
        $stmt = $this->pdo->prepare("
            INSERT OR REPLACE INTO orders (id, customer_email, items, total, status, created_at)
            VALUES (:id, :email, :items, :total, :status, :created_at)
        ");
        $stmt->execute([
            ':id' => $order->getId(),
            ':email' => $order->getCustomerEmail(),
            ':items' => json_encode($order->getItems()),
            ':total' => $order->getTotal(),
            ':status' => $order->getStatus(),
            ':created_at' => $order->getCreatedAt()->format('c'),
        ]);
    }

    public function find(string $id): ?Order
    {
        $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    /** @return Order[] */
    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM orders ORDER BY created_at DESC");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map([$this, 'hydrate'], $rows);
    }

    private function hydrate(array $row): Order
    {
        return Order::reconstruct(
            $row['id'],
            $row['customer_email'],
            json_decode($row['items'], true),
            (float) $row['total'],
            $row['status'],
            new \DateTimeImmutable($row['created_at'])
        );
    }
}
