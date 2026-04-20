<?php

namespace App\Entity;

use App\Repository\SeatReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeatReservationRepository::class)]
#[ORM\Index(name: 'idx_reservation_status_expires', columns: ['status', 'expires_at'])]
class SeatReservation
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_EXPIRED   = 'expired';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'seatReservations')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: TicketTier::class, inversedBy: 'seatReservations')]
    #[ORM\JoinColumn(nullable: false)]
    private TicketTier $ticketTier;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quantity;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $reservedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $expiresAt;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\OneToOne(targetEntity: BookingItem::class, mappedBy: 'seatReservation')]
    private ?BookingItem $bookingItem = null;

    public function __construct()
    {
        $this->reservedAt = new \DateTime();
        $this->expiresAt  = new \DateTime('+10 minutes');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getReservedAt(): \DateTimeInterface
    {
        return $this->reservedAt;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTime();
    }

    public function getBookingItem(): ?BookingItem
    {
        return $this->bookingItem;
    }
}
