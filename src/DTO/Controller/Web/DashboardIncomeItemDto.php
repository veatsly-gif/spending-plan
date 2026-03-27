<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class DashboardIncomeItemDto
{
    public function __construct(
        public int $id,
        public string $username,
        public string $amount,
        public string $currencyCode,
        public ?string $amountInGel,
        public ?string $officialRatedAmountInGel,
        public ?string $rate,
        public ?string $comment,
        public string $createdAtLabel,
    ) {
    }
}
