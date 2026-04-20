<?php

namespace App\Entity;

use App\Repository\SeatRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeatRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_seat_event', columns: ['event_id', 'seat_number'])]
class Seat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'seats')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: TicketTier::class)]
    #[ORM\JoinColumn(nullable: false)]
    private TicketTier $ticketTier;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $seatNumber;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isAssigned = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): static
    {
        $this->event = $event;
        return $this;
    }

    public function getTicketTier(): TicketTier
    {
        return $this->ticketTier;
    }

    public function setTicketTier(TicketTier $ticketTier): static
    {
        $this->ticketTier = $ticketTier;
        return $this;
    }

    public function getSeatNumber(): string
    {
        return $this->seatNumber;
    }

    public function setSeatNumber(string $seatNumber): static
    {
        $this->seatNumber = $seatNumber;
        return $this;
    }

    public function isAssigned(): bool
    {
        return $this->isAssigned;
    }

    public function setIsAssigned(bool $isAssigned): static
    {
        $this->isAssigned = $isAssigned;
        return $this;
    }
}
