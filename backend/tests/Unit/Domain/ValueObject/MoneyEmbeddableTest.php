<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\MoneyEmbeddable;
use Money\Money;
use PHPUnit\Framework\TestCase;

class MoneyEmbeddableTest extends TestCase
{
    // --- Constructor valid scenarios ---

    public function testConstructorWithValidAmountAndCurrency(): void
    {
        $money = new MoneyEmbeddable(89999, 'USD');

        $this->assertSame(89999, $money->getAmount());
        $this->assertSame('USD', $money->getCurrency());
    }

    public function testConstructorWithLargeAmount(): void
    {
        $money = new MoneyEmbeddable(PHP_INT_MAX, 'USD');

        $this->assertSame(PHP_INT_MAX, $money->getAmount());
    }

    // --- Constructor rejects non-positive amount ---

    public function testConstructorRejectsZeroAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        new MoneyEmbeddable(0, 'USD');
    }

    public function testConstructorRejectsNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        new MoneyEmbeddable(-1, 'USD');
    }

    // --- ofUSD factory ---

    public function testOfUSDFactoryCreatesMoneyEmbeddable(): void
    {
        $money = MoneyEmbeddable::ofUSD(4250);

        $this->assertSame(4250, $money->getAmount());
        $this->assertSame('USD', $money->getCurrency());
    }

    // --- toArray ---

    public function testToArrayReturnsAmountAndCurrency(): void
    {
        $money = new MoneyEmbeddable(89999, 'USD');
        $array = $money->toArray();

        $this->assertSame(['amount' => 89999, 'currency' => 'USD'], $array);
    }

    public function testToArrayWithDifferentAmount(): void
    {
        $money = new MoneyEmbeddable(100, 'USD');
        $array = $money->toArray();

        $this->assertSame(['amount' => 100, 'currency' => 'USD'], $array);
    }

    // --- getMoney wraps Money\Money ---

    public function testGetMoneyReturnsMoneyObjectWithCorrectValues(): void
    {
        $embeddable = new MoneyEmbeddable(89999, 'USD');
        $money = $embeddable->getMoney();

        $this->assertInstanceOf(Money::class, $money);
        $this->assertSame('89999', $money->getAmount());
        $this->assertSame('USD', $money->getCurrency()->getCode());
    }

    // --- Serialization round-trip for Messenger ---

    public function testSerializationRoundTrip(): void
    {
        $original = new MoneyEmbeddable(4250, 'USD');
        $serialized = serialize($original);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(MoneyEmbeddable::class, $unserialized);
        $this->assertSame(4250, $unserialized->getAmount());
        $this->assertSame('USD', $unserialized->getCurrency());
    }
}