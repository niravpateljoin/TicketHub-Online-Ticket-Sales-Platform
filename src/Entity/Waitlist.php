<?php

namespace App\Entity;

use App\Repository\WaitlistRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WaitlistRepository::class)]
#[ORM\Table(name: 'waitlist')]
#[ORM\Index(columns: ['ticket_tier_id', 'status'], name: 'idx_waitlist_tier_status')]
#[ORM\Index(columns: ['user_id'], name: 'idx_waitlist_user')]
class Waitlist
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_NOTIFIED  = 'notified';
    public const STATUS_BOOKED    = 'booked';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\ManyToOne(targetEntity: TicketTier::class)]
    #[ORM\JoinColumn(name: 'ticket_tier_id', nullable: false, onDelete: 'CASCADE')]
    private TicketTier $ticketTier;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $joinedAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $notifiedAt = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = self::STATUS_PENDING;

    public function __construct()
    {
        $this->joinedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getEvent(): Event { return $this->event; }
    public function setEvent(Event $event): static { $this->event = $event; return $this; }

    public function getTicketTier(): TicketTier { return $this->ticketTier; }
    public function setTicketTier(TicketTier $ticketTier): static { $this->ticketTier = $ticketTier; return $this; }

    public function getJoinedAt(): \DateTimeInterface { return $this->joinedAt; }

    public function getNotifiedAt(): ?\DateTimeInterface { return $this->notifiedAt; }
    public function setNotifiedAt(?\DateTimeInterface $notifiedAt): static { $this->notifiedAt = $notifiedAt; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
}
