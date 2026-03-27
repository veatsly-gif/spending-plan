<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpendingPlanRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpendingPlanRepository::class)]
#[ORM\Table(name: 'spending_plan')]
#[ORM\Index(name: 'idx_spending_plan_date_from', columns: ['date_from'])]
#[ORM\Index(name: 'idx_spending_plan_date_to', columns: ['date_to'])]
#[ORM\Index(name: 'idx_spending_plan_plan_type', columns: ['plan_type'])]
class SpendingPlan
{
    public const PLAN_TYPE_WEEKDAY = 'weekday';
    public const PLAN_TYPE_WEEKEND = 'weekend';
    public const PLAN_TYPE_REGULAR = 'regular';
    public const PLAN_TYPE_PLANNED = 'planned';
    public const PLAN_TYPE_CUSTOM = 'custom';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 24)]
    private string $planType = self::PLAN_TYPE_CUSTOM;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dateFrom;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dateTo;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $limitAmount = '0.00';

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Currency $currency = null;

    #[ORM\Column]
    private int $weight = 1;

    #[ORM\Column]
    private bool $isSystem = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $today = $now->setTime(0, 0);

        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->dateFrom = $today;
        $this->dateTo = $today;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getPlanType(): string
    {
        return $this->planType;
    }

    public function setPlanType(string $planType): self
    {
        $this->planType = trim($planType);

        return $this;
    }

    public function getDateFrom(): \DateTimeImmutable
    {
        return $this->dateFrom;
    }

    public function setDateFrom(\DateTimeImmutable $dateFrom): self
    {
        $this->dateFrom = $dateFrom->setTime(0, 0);

        return $this;
    }

    public function getDateTo(): \DateTimeImmutable
    {
        return $this->dateTo;
    }

    public function setDateTo(\DateTimeImmutable $dateTo): self
    {
        $this->dateTo = $dateTo->setTime(0, 0);

        return $this;
    }

    public function getLimitAmount(): string
    {
        return $this->limitAmount;
    }

    public function setLimitAmount(string $limitAmount): self
    {
        $this->limitAmount = $limitAmount;

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): self
    {
        $this->isSystem = $isSystem;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = null !== $note ? trim($note) : null;

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

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
