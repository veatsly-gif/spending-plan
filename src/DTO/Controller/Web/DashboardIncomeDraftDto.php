<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

use App\Entity\Currency;

final class DashboardIncomeDraftDto
{
    private string $amount = '';
    private ?Currency $currency = null;
    private ?string $comment = null;
    private bool $convertToGel = true;

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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = null !== $comment ? trim($comment) : null;

        return $this;
    }

    public function isConvertToGel(): bool
    {
        return $this->convertToGel;
    }

    public function setConvertToGel(bool $convertToGel): self
    {
        $this->convertToGel = $convertToGel;

        return $this;
    }
}
