<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class DashboardIncomeWidgetDto
{
    public function __construct(
        public ?DashboardIncomeItemDto $lastIncome,
        public string $monthLabel,
        public int $monthIncomeCount,
        public string $monthIncomeAmountGel,
        public ?string $eurGelRate,
        public ?string $usdtGelRate,
        public ?string $ratesUpdatedAtLabel,
    ) {
    }
}
