<?php

namespace App\Dto\Organizer;

use Symfony\Component\Validator\Constraints as Assert;

class UpsertTierDto
{
    #[Assert\NotBlank(message: 'Tier name is required.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'Price is required.')]
    #[Assert\Regex(pattern: '/^\d+$/', message: 'Price must be a whole number.')]
    public string $price = '';

    #[Assert\NotBlank(message: 'Total seats is required.')]
    #[Assert\Regex(pattern: '/^\d+$/', message: 'Total seats must be a whole number.')]
    public string $totalSeats = '';

    #[Assert\Regex(
        pattern: '/^$|^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/',
        message: 'Sale start must be a valid datetime.'
    )]
    public string $saleStartsAt = '';

    #[Assert\Regex(
        pattern: '/^$|^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/',
        message: 'Sale end must be a valid datetime.'
    )]
    public string $saleEndsAt = '';

    public string $description = '';
}
