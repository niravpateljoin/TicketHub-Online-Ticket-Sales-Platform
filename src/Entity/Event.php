<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'events')]
#[Vich\Uploadable]
class Event
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SOLD_OUT  = 'sold_out';
    public const STATUS_POSTPONED = 'postponed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Organizer::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private Organizer $organizer;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private Category $category;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $slug = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateTime;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $venueName;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $venueAddress = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isOnline = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $bannerImageName = null;

    #[Vich\UploadableField(mapping: 'event_banners', fileNameProperty: 'bannerImageName')]
    private ?File $bannerImageFile = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToMany(targetEntity: TicketTier::class, mappedBy: 'event', cascade: ['persist', 'remove'])]
    private Collection $ticketTiers;

    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'event')]
    private Collection $bookings;

    #[ORM\OneToMany(targetEntity: Seat::class, mappedBy: 'event', cascade: ['persist', 'remove'])]
    private Collection $seats;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->ticketTiers = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->seats = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizer(): Organizer
    {
        return $this->organizer;
    }

    public function setOrganizer(Organizer $organizer): static
    {
        $this->organizer = $organizer;
        return $this;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): static
    {
        $this->category = $category;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDateTime(): \DateTimeInterface
    {
        return $this->dateTime;
    }

    public function setDateTime(\DateTimeInterface $dateTime): static
    {
        $this->dateTime = $dateTime;
        return $this;
    }

    public function getVenueName(): string
    {
        return $this->venueName;
    }

    public function setVenueName(string $venueName): static
    {
        $this->venueName = $venueName;
        return $this;
    }

    public function getVenueAddress(): ?string
    {
        return $this->venueAddress;
    }

    public function setVenueAddress(?string $venueAddress): static
    {
        $this->venueAddress = $venueAddress;
        return $this;
    }

    public function isOnline(): bool
    {
        return $this->isOnline;
    }

    public function setIsOnline(bool $isOnline): static
    {
        $this->isOnline = $isOnline;
        return $this;
    }

    public function getBannerImageName(): ?string
    {
        return $this->bannerImageName;
    }

    public function setBannerImageName(?string $bannerImageName): static
    {
        $this->bannerImageName = $bannerImageName;
        return $this;
    }

    public function getBannerImageFile(): ?File
    {
        return $this->bannerImageFile;
    }

    public function setBannerImageFile(?File $bannerImageFile): static
    {
        $this->bannerImageFile = $bannerImageFile;
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

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getTicketTiers(): Collection
    {
        return $this->ticketTiers;
    }

    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function getSeats(): Collection
    {
        return $this->seats;
    }
}
