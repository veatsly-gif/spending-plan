<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserMetadataRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserMetadataRepository::class)]
#[ORM\Table(name: 'users_metadata')]
#[ORM\Index(name: 'idx_users_metadata_user_id', columns: ['user_id'])]
class UserMetadata
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'json')]
    private array $preferences = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getPreferences(): array
    {
        return $this->preferences;
    }

    public function setPreferences(array $preferences): self
    {
        $this->preferences = $preferences;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getPreference(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->preferences) ? $this->preferences[$key] : $default;
    }

    public function setPreference(string $key, mixed $value): self
    {
        $this->preferences[$key] = $value;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
