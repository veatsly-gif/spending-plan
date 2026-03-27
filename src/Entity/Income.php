<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\IncomeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IncomeRepository::class)]
#[ORM\Table(name: 'income')]
#[ORM\Index(name: 'idx_income_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_income_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_income_amount_in_gel', columns: ['amount_in_gel'])]
#[ORM\Index(name: 'idx_income_official_rated_amount_in_gel', columns: ['official_rated_amount_in_gel'])]
class Income
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'RESTRICT')]
    private ?User $userAdded = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(name: 'currency_id', nullable: false, onDelete: 'RESTRICT')]
    private ?Currency $currency = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, nullable: true)]
    private ?string $amountInGel = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    private ?string $rate = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4, nullable: true)]
    private ?string $officialRatedAmountInGel = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

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

    public function getUserAdded(): ?User
    {
        return $this->userAdded;
    }

    public function setUserAdded(User $userAdded): self
    {
        $this->userAdded = $userAdded;

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

    public function getAmountInGel(): ?string
    {
        return $this->amountInGel;
    }

    public function setAmountInGel(?string $amountInGel): self
    {
        $this->amountInGel = $amountInGel;

        return $this;
    }

    public function getRate(): ?string
    {
        return $this->rate;
    }

    public function setRate(?string $rate): self
    {
        $this->rate = $rate;

        return $this;
    }

    public function getOfficialRatedAmountInGel(): ?string
    {
        return $this->officialRatedAmountInGel;
    }

    public function setOfficialRatedAmountInGel(?string $officialRatedAmountInGel): self
    {
        $this->officialRatedAmountInGel = $officialRatedAmountInGel;

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
