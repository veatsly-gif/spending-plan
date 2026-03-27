<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class DashboardSpendWidgetDto
{
    public function __construct(
        public ?DashboardSpendItemDto $lastSpend,
        public string $monthLabel,
        public int $monthSpendCount,
        public string $monthSpendAmountLabel,
    ) {
    }
}
