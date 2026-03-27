<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SpendRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpendRepository::class)]
#[ORM\Table(name: 'spend')]
#[ORM\Index(name: 'idx_spend_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_spend_spending_plan', columns: ['spending_plan_id'])]
#[ORM\Index(name: 'idx_spend_currency', columns: ['currency_id'])]
#[ORM\Index(name: 'idx_spend_spend_date', columns: ['spend_date'])]
#[ORM\Index(name: 'idx_spend_created_at', columns: ['created_at'])]
class Spend
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'RESTRICT')]
    private ?User $userAdded = null;

    #[ORM\ManyToOne(targetEntity: SpendingPlan::class)]
    #[ORM\JoinColumn(name: 'spending_plan_id', nullable: false, onDelete: 'RESTRICT')]
    private ?SpendingPlan $spendingPlan = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(name: 'currency_id', nullable: false, onDelete: 'RESTRICT')]
    private ?Currency $currency = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $spendDate;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->spendDate = (new \DateTimeImmutable())->setTime(0, 0);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserAdded(): ?User
    {
        return $this->userAdded;
    }

    public function setUserAdded(User $userAdded): self
    {
        $this->userAdded = $userAdded;

        return $this;
    }

    public function getSpendingPlan(): ?SpendingPlan
    {
        return $this->spendingPlan;
    }

    public function setSpendingPlan(SpendingPlan $spendingPlan): self
    {
        $this->spendingPlan = $spendingPlan;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;

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

    public function getSpendDate(): \DateTimeImmutable
    {
        return $this->spendDate;
    }

    public function setSpendDate(\DateTimeImmutable $spendDate): self
    {
        $this->spendDate = $spendDate->setTime(0, 0);

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = null !== $comment ? trim($comment) : null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
