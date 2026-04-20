<?php

namespace App\Dto\Organizer;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateEventStatusDto
{
    #[Assert\NotBlank(message: 'Status is required.')]
    #[Assert\Choice(
        choices: ['active', 'sold_out', 'postponed', 'cancelled'],
        message: 'Unsupported event status.'
    )]
    public string $status = '';
}
