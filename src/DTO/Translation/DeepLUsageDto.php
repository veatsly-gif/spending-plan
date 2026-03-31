<?php

declare(strict_types=1);

namespace App\DTO\Translation;

final readonly class DeepLUsageDto
{
    public function __construct(
        public int $characterCount,
        public int $characterLimit,
    ) {
    }

    public function remainingCharacters(): int
    {
        if ($this->characterLimit <= 0) {
            return PHP_INT_MAX;
        }

        return max(0, $this->characterLimit - $this->characterCount);
    }

    public function usagePercent(): float
    {
        if ($this->characterLimit <= 0) {
            return 0.0;
        }

        return max(0.0, min(100.0, ($this->characterCount * 100) / $this->characterLimit));
    }
}
