<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\CreateOrderRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateOrderRequestTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testNotBlankConstraintOnCustomerEmail(): void
    {
        $dto = new CreateOrderRequest(customerEmail: '', items: ['widget'], total: 9.99);
        $violations = $this->validator->validate($dto);

        $emailViolations = array_filter(
            iterator_to_array($violations),
            fn($v) => $v->getPropertyPath() === 'customerEmail'
                    && $v->getMessage() === 'This value should not be blank.'
        );

        $this->assertGreaterThan(0, count($emailViolations), 'Expected NotBlank violation on customerEmail');
    }

    public function testEmailConstraintOnCustomerEmail(): void
    {
        $dto = new CreateOrderRequest(customerEmail: 'not-an-email', items: ['widget'], total: 9.99);
        $violations = $this->validator->validate($dto);

        $emailViolations = array_filter(
            iterator_to_array($violations),
            fn($v) => $v->getPropertyPath() === 'customerEmail'
                    && $v->getMessage() === 'This value is not a valid email address.'
        );

        $this->assertGreaterThan(0, count($emailViolations), 'Expected Email violation on customerEmail');
    }

    public function testNotNullAndCountConstraintOnItems(): void
    {
        // Test null items
        $dto = new CreateOrderRequest(customerEmail: 'test@example.com', items: null, total: 9.99);
        $violations = $this->validator->validate($dto);

        $nullViolations = array_filter(
            iterator_to_array($violations),
            fn($v) => $v->getPropertyPath() === 'items'
                    && $v->getMessage() === 'This value should not be null.'
        );

        $this->assertGreaterThan(0, count($nullViolations), 'Expected NotNull violation on items');

        // Test empty items
        $dto = new CreateOrderRequest(customerEmail: 'test@example.com', items: [], total: 9.99);
        $violations = $this->validator->validate($dto);

        $countViolations = array_filter(
            iterator_to_array($violations),
            fn($v) => $v->getPropertyPath() === 'items'
                    && $v->getMessage() === 'This collection should contain at least 1 element.'
        );

        $this->assertGreaterThan(0, count($countViolations), 'Expected Count(min:1) violation on items');
    }

    public function testNotNullConstraintOnTotal(): void
    {
        $dto = new CreateOrderRequest(customerEmail: 'test@example.com', items: ['widget'], total: null);
        $violations = $this->validator->validate($dto);

        $totalViolations = array_filter(
            iterator_to_array($violations),
            fn($v) => $v->getPropertyPath() === 'total'
                    && $v->getMessage() === 'This value should not be null.'
        );

        $this->assertGreaterThan(0, count($totalViolations), 'Expected NotNull violation on total');
    }

    public function testGreaterThanConstraintOnTotal(): void
    {
        // Test zero total
        $dto = new CreateOrderRequest(customerEmail: 'test@example.com', items: ['widget'], total: 0.0);
        $violations = $this->validator->validate($dto);

        $zeroViolations = array_filter(
            iterator_to_array($violations),
            fn($v) => $v->getPropertyPath() === 'total'
                    && $v->getMessage() === 'This value should be greater than 0.'
        );

        $this->assertGreaterThan(0, count($zeroViolations), 'Expected GreaterThan(0) violation on total=0');

        // Test negative total
        $dto = new CreateOrderRequest(customerEmail: 'test@example.com', items: ['widget'], total: -5.0);
        $violations = $this->validator->validate($dto);

        $negViolations = array_filter(
            iterator_to_array($violations),
            fn($v) => $v->getPropertyPath() === 'total'
                    && $v->getMessage() === 'This value should be greater than 0.'
        );

        $this->assertGreaterThan(0, count($negViolations), 'Expected GreaterThan(0) violation on negative total');
    }

    public function testValidCreateOrderRequestHasNoViolations(): void
    {
        $dto = new CreateOrderRequest(
            customerEmail: 'valid@example.com',
            items: ['widget'],
            total: 9.99,
        );
        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations, 'A valid DTO should have zero violations');
    }
}