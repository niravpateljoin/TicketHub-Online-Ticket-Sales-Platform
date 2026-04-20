<?php

namespace App\Entity;

use App\Repository\TicketTierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketTierRepository::class)]
class TicketTier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'ticketTiers')]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    #[ORM\Column(type: Types::INTEGER)]
    private int $basePrice;

    #[ORM\Column(type: Types::INTEGER)]
    private int $totalSeats;

    #[ORM\Column(type: Types::INTEGER)]
    private int $soldCount = 0;

    /**
     * Doctrine Optimistic Locking — prevents concurrent over-selling.
     * Incremented automatically by Doctrine on each UPDATE.
     */
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $saleStartsAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $saleEndsAt = null;

    #[ORM\OneToMany(targetEntity: SeatReservation::class, mappedBy: 'ticketTier')]
    private Collection $seatReservations;

    #[ORM\OneToMany(targetEntity: BookingItem::class, mappedBy: 'ticketTier')]
    private Collection $bookingItems;

    public function __construct()
    {
        $this->seatReservations = new ArrayCollection();
        $this->bookingItems = new ArrayCollection();
    }

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getBasePrice(): int
    {
        return $this->basePrice;
    }

    public function setBasePrice(int $basePrice): static
    {
        $this->basePrice = $basePrice;
        return $this;
    }

    public function getTotalSeats(): int
    {
        return $this->totalSeats;
    }

    public function setTotalSeats(int $totalSeats): static
    {
        $this->totalSeats = $totalSeats;
        return $this;
    }

    public function getSoldCount(): int
    {
        return $this->soldCount;
    }

    public function setSoldCount(int $soldCount): static
    {
        $this->soldCount = $soldCount;
        return $this;
    }

    public function getAvailableSeats(): int
    {
        return $this->totalSeats - $this->soldCount;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getSaleStartsAt(): ?\DateTimeInterface
    {
        return $this->saleStartsAt;
    }

    public function setSaleStartsAt(?\DateTimeInterface $saleStartsAt): static
    {
        $this->saleStartsAt = $saleStartsAt;
        return $this;
    }

    public function getSaleEndsAt(): ?\DateTimeInterface
    {
        return $this->saleEndsAt;
    }

    public function setSaleEndsAt(?\DateTimeInterface $saleEndsAt): static
    {
        $this->saleEndsAt = $saleEndsAt;
        return $this;
    }

    public function getSeatReservations(): Collection
    {
        return $this->seatReservations;
    }

    public function getBookingItems(): Collection
    {
        return $this->bookingItems;
    }
}
