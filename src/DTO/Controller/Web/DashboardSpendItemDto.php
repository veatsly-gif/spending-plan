<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class DashboardSpendItemDto
{
    public function __construct(
        public int $id,
        public string $username,
        public string $amount,
        public string $currencyCode,
        public string $spendingPlanName,
        public string $spendDateLabel,
        public ?string $comment,
        public string $createdAtLabel,
    ) {
    }
}
