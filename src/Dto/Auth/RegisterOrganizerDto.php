<?php

namespace App\Dto\Auth;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterOrganizerDto
{
    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Please provide a valid email address.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Password is required.')]
    #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters.')]
    public string $password = '';

    #[Assert\Length(max: 255, maxMessage: 'Organization name cannot exceed 255 characters.')]
    public string $organizationName = '';

    #[Assert\Length(max: 30, maxMessage: 'Phone number cannot exceed 30 characters.')]
    #[Assert\Regex(
        pattern: '/^$|^[+\d\s\-(). ]{1,30}$/',
        message: 'Please enter a valid phone number.'
    )]
    public string $phone = '';

    #[Assert\Length(max: 2000, maxMessage: 'Description cannot exceed 2000 characters.')]
    public string $description = '';
}
