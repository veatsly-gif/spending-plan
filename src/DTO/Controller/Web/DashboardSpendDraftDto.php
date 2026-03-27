<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

use App\Entity\Currency;
use App\Entity\SpendingPlan;

final class DashboardSpendDraftDto
{
    private string $amount = '';
    private ?Currency $currency = null;
    private ?SpendingPlan $spendingPlan = null;
    private \DateTimeImmutable $spendDate;
    private ?string $comment = null;

    public function __construct()
    {
        $this->spendDate = (new \DateTimeImmutable())->setTime(0, 0);
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = trim($amount);

        return $this;
    }

    public function getCurrency(): ?Currency
    {
        return $this->currency;
    }

    public function setCurrency(?Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getSpendingPlan(): ?SpendingPlan
    {
        return $this->spendingPlan;
    }

    public function setSpendingPlan(?SpendingPlan $spendingPlan): self
    {
        $this->spendingPlan = $spendingPlan;

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
}
