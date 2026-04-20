<?php

namespace App\Dto\Organizer;

use Symfony\Component\Validator\Constraints as Assert;

class UpsertEventDto
{
    #[Assert\NotBlank(message: 'Event name is required.')]
    #[Assert\Length(max: 255, maxMessage: 'Event name cannot exceed 255 characters.')]
    public string $name = '';

    #[Assert\Regex(
        pattern: '/^$|^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        message: 'Slug must use lowercase letters, numbers, and hyphens only.'
    )]
    #[Assert\Length(max: 255, maxMessage: 'Slug cannot exceed 255 characters.')]
    public string $slug = '';

    #[Assert\Length(max: 5000, maxMessage: 'Description cannot exceed 5000 characters.')]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'Category is required.')]
    public string $category = '';

    #[Assert\NotBlank(message: 'Start date is required.')]
    #[Assert\Date(message: 'Start date must be a valid date.')]
    public string $startDate = '';

    #[Assert\Regex(
        pattern: '/^$|^\d{2}:\d{2}$/',
        message: 'Start time must use HH:MM format.'
    )]
    public string $startTime = '';

    #[Assert\Regex(
        pattern: '/^$|^\d{4}-\d{2}-\d{2}$/',
        message: 'End date must be a valid date.'
    )]
    public string $endDate = '';

    #[Assert\Length(max: 255, maxMessage: 'Venue name cannot exceed 255 characters.')]
    public string $venueName = '';

    #[Assert\Length(max: 500, maxMessage: 'Venue address cannot exceed 500 characters.')]
    public string $venueAddress = '';

    public bool $isOnline = false;

    #[Assert\Regex(
        pattern: '/^$|^\d+$/',
        message: 'Max attendees must be a whole number.'
    )]
    public string $maxAttendees = '';
}
