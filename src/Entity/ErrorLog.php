<?php

namespace App\Entity;

use App\Repository\ErrorLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ErrorLogRepository::class)]
#[ORM\Table(name: 'error_log')]
#[ORM\Index(columns: ['occurred_at', 'status_code'], name: 'idx_error_log_occurred_status')]
class ErrorLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 500)]
    private string $message = '';

    #[ORM\Column(length: 255)]
    private string $exceptionClass = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $stackTrace = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(length: 10)]
    private string $method = 'GET';

    #[ORM\Column]
    private int $statusCode = 500;

    #[ORM\Column(nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $resolved = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNote = null;

    public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }

    public function getExceptionClass(): string { return $this->exceptionClass; }
    public function setExceptionClass(string $exceptionClass): static { $this->exceptionClass = $exceptionClass; return $this; }

    public function getStackTrace(): ?string { return $this->stackTrace; }
    public function setStackTrace(?string $stackTrace): static { $this->stackTrace = $stackTrace; return $this; }

    public function getRoute(): ?string { return $this->route; }
    public function setRoute(?string $route): static { $this->route = $route; return $this; }

    public function getMethod(): string { return $this->method; }
    public function setMethod(string $method): static { $this->method = $method; return $this; }

    public function getStatusCode(): int { return $this->statusCode; }
    public function setStatusCode(int $statusCode): static { $this->statusCode = $statusCode; return $this; }

    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(?int $userId): static { $this->userId = $userId; return $this; }

    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ipAddress): static { $this->ipAddress = $ipAddress; return $this; }

    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function setOccurredAt(\DateTimeImmutable $occurredAt): static { $this->occurredAt = $occurredAt; return $this; }

    public function isResolved(): bool { return $this->resolved; }
    public function setResolved(bool $resolved): static { $this->resolved = $resolved; return $this; }

    public function getAdminNote(): ?string { return $this->adminNote; }
    public function setAdminNote(?string $adminNote): static { $this->adminNote = $adminNote; return $this; }
}
