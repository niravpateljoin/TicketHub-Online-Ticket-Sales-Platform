<?php

namespace App\Entity;

use App\Repository\BookingItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingItemRepository::class)]
class BookingItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'bookingItems')]
    #[ORM\JoinColumn(nullable: false)]
    private Booking $booking;

    #[ORM\ManyToOne(targetEntity: TicketTier::class, inversedBy: 'bookingItems')]
    #[ORM\JoinColumn(nullable: false)]
    private TicketTier $ticketTier;

    #[ORM\OneToOne(targetEntity: SeatReservation::class, inversedBy: 'bookingItem')]
    #[ORM\JoinColumn(nullable: true)]
    private ?SeatReservation $seatReservation = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantity;

    #[ORM\Column(type: Types::INTEGER)]
    private int $unitPrice;

    #[ORM\OneToOne(targetEntity: ETicket::class, mappedBy: 'bookingItem')]
    private ?ETicket $eTicket = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    public function setBooking(Booking $booking): static
    {
        $this->booking = $booking;
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

    public function getSeatReservation(): ?SeatReservation
    {
        return $this->seatReservation;
    }

    public function setSeatReservation(?SeatReservation $seatReservation): static
    {
        $this->seatReservation = $seatReservation;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getUnitPrice(): int
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(int $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        return $this;
    }

    public function getSubtotal(): int
    {
        return $this->unitPrice * $this->quantity;
    }

    public function getETicket(): ?ETicket
    {
        return $this->eTicket;
    }
}
