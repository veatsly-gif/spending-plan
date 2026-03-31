<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ApiLimitRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiLimitRepository::class)]
#[ORM\Table(name: 'api_limit')]
#[ORM\Index(name: 'idx_api_limit_provider', columns: ['provider'])]
#[ORM\Index(name: 'idx_api_limit_created_at', columns: ['created_at'])]
class ApiLimit
{
    public const PROVIDER_DEEPL = 'deepl';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $provider = self::PROVIDER_DEEPL;

    #[ORM\Column(type: 'bigint')]
    private string $characterCount = '0';

    #[ORM\Column(type: 'bigint')]
    private string $characterLimit = '0';

    #[ORM\Column(type: 'bigint')]
    private string $remainingCharacters = '0';

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private string $usagePercent = '0.00';

    #[ORM\Column]
    private bool $closeToLimit = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = mb_strtolower(trim($provider));

        return $this;
    }

    public function getCharacterCount(): int
    {
        return (int) $this->characterCount;
    }

    public function setCharacterCount(int $characterCount): self
    {
        $this->characterCount = (string) max(0, $characterCount);

        return $this;
    }

    public function getCharacterLimit(): int
    {
        return (int) $this->characterLimit;
    }

    public function setCharacterLimit(int $characterLimit): self
    {
        $this->characterLimit = (string) max(0, $characterLimit);

        return $this;
    }

    public function getRemainingCharacters(): int
    {
        return (int) $this->remainingCharacters;
    }

    public function setRemainingCharacters(int $remainingCharacters): self
    {
        $this->remainingCharacters = (string) max(0, $remainingCharacters);

        return $this;
    }

    public function getUsagePercent(): string
    {
        return $this->usagePercent;
    }

    public function setUsagePercent(float $usagePercent): self
    {
        $bounded = max(0.0, min(100.0, $usagePercent));
        $this->usagePercent = number_format($bounded, 2, '.', '');

        return $this;
    }

    public function isCloseToLimit(): bool
    {
        return $this->closeToLimit;
    }

    public function setCloseToLimit(bool $closeToLimit): self
    {
        $this->closeToLimit = $closeToLimit;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
