<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateProfileDto
{
    #[Assert\NotBlank(message: 'Name is required.')]
    #[Assert\Length(max: 120, maxMessage: 'Name must be 120 characters or fewer.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Please provide a valid email address.')]
    public string $email = '';

    #[Assert\Email(message: 'Please provide a valid new email address.')]
    public string $newEmail = '';

    public string $password = '';
}
