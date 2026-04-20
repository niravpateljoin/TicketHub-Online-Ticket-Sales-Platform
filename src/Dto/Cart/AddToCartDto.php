<?php

namespace App\Dto\Cart;

use Symfony\Component\Validator\Constraints as Assert;

class AddToCartDto
{
    #[Assert\NotBlank(message: 'Tier is required.')]
    #[Assert\Regex(pattern: '/^\d+$/', message: 'Tier ID must be numeric.')]
    public string $tierId = '';

    #[Assert\NotBlank(message: 'Quantity is required.')]
    #[Assert\Regex(pattern: '/^[1-9]\d{0,3}$/', message: 'Quantity must be a number between 1 and 9999.')]
    public string $quantity = '';
}
