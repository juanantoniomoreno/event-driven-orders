<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class MoneyInput
{
    public function __construct(
        #[Assert\NotNull(message: 'This value should not be null.')]
        #[Assert\GreaterThan(0, message: 'This value should be greater than 0.')]
        public ?int $amount = null,

        #[Assert\NotBlank(message: 'This value should not be blank.')]
        #[Assert\Choice(choices: ['USD'], message: 'This value should be one of the choices: USD.')]
        public ?string $currency = null,
    ) {
    }
}