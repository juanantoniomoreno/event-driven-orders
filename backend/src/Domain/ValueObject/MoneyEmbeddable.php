<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use Doctrine\ORM\Mapping as ORM;
use Money\Money as MoneyLib;
use Money\Currency;

#[ORM\Embeddable]
final class MoneyEmbeddable
{
    #[ORM\Column(type: 'bigint')]
    private int $amount;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    public function __construct(int $amount, string $currency)
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }

        $this->amount = $amount;
        $this->currency = $currency;
    }

    public static function ofUSD(int $cents): self
    {
        return new self($cents, 'USD');
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    public function getMoney(): MoneyLib
    {
        return new MoneyLib((string) $this->amount, new Currency($this->currency));
    }
}