<?php

namespace App\Entity;

use App\Repository\ETicketRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ETicketRepository::class)]
#[ORM\Table(name: 'e_ticket')]
class ETicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: BookingItem::class, inversedBy: 'eTicket')]
    #[ORM\JoinColumn(nullable: false)]
    private BookingItem $bookingItem;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $qrToken;

    /**
     * Path to the PDF stored outside the web root (var/tickets/).
     * Null until the PDF has been generated (async — set by ETicketGeneratorService).
     */
    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $filePath = null;

    /**
     * Timestamp of PDF generation. Null until generated.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $generatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $checkedInAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'checked_in_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $checkedInBy = null;

    public function __construct()
    {
        $this->qrToken = bin2hex(random_bytes(32));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBookingItem(): BookingItem
    {
        return $this->bookingItem;
    }

    public function setBookingItem(BookingItem $bookingItem): static
    {
        $this->bookingItem = $bookingItem;
        return $this;
    }

    public function getQrToken(): string
    {
        return $this->qrToken;
    }

    public function setQrToken(string $qrToken): static
    {
        $this->qrToken = $qrToken;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeInterface
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(?\DateTimeInterface $generatedAt): static
    {
        $this->generatedAt = $generatedAt;
        return $this;
    }

    public function getCheckedInAt(): ?\DateTimeInterface
    {
        return $this->checkedInAt;
    }

    public function setCheckedInAt(?\DateTimeInterface $checkedInAt): static
    {
        $this->checkedInAt = $checkedInAt;
        return $this;
    }

    public function getCheckedInBy(): ?User
    {
        return $this->checkedInBy;
    }

    public function setCheckedInBy(?User $checkedInBy): static
    {
        $this->checkedInBy = $checkedInBy;
        return $this;
    }

    public function isCheckedIn(): bool
    {
        return $this->checkedInAt !== null;
    }

    public function isPdfReady(): bool
    {
        return $this->filePath !== null && is_file($this->filePath);
    }
}
