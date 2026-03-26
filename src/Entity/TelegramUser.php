<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TelegramUserRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TelegramUserRepository::class)]
#[ORM\Table(name: 'telegram_user')]
#[ORM\UniqueConstraint(name: 'uniq_telegram_user_telegram_id', columns: ['telegram_id'])]
class TelegramUser
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'bigint', unique: true)]
    private string $telegramId;

    #[ORM\Column(length: 255)]
    private string $firstName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 24)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $authorizedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTelegramId(): string
    {
        return $this->telegramId;
    }

    public function setTelegramId(string $telegramId): self
    {
        $this->telegramId = $telegramId;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = null !== $lastName ? trim($lastName) : null;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAuthorizedAt(): ?\DateTimeImmutable
    {
        return $this->authorizedAt;
    }

    public function setAuthorizedAt(?\DateTimeImmutable $authorizedAt): self
    {
        $this->authorizedAt = $authorizedAt;

        return $this;
    }
}
