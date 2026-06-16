<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateOrderRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'This value should not be blank.')]
        #[Assert\Email(message: 'This value is not a valid email address.')]
        public ?string $customerEmail = null,

        #[Assert\NotNull(message: 'This value should not be null.')]
        #[Assert\Count(min: 1, minMessage: 'This collection should contain at least 1 element.')]
        public ?array $items = null,

        #[Assert\NotNull(message: 'This value should not be null.')]
        #[Assert\Valid]
        public ?MoneyInput $total = null,
    ) {
    }
}