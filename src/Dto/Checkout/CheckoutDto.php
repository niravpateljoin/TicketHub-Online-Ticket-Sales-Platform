<?php

namespace App\Dto\Checkout;

use Symfony\Component\Validator\Constraints as Assert;

class CheckoutDto
{
    #[Assert\Length(max: 128, maxMessage: 'Idempotency key cannot exceed 128 characters.')]
    public string $idempotencyKey = '';
}
