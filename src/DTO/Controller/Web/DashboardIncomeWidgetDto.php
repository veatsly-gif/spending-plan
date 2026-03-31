<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class DashboardIncomeWidgetDto
{
    public function __construct(
        public string $monthLabel,
        public string $totalIncomeGel,
        public string $regularAndPlannedGel,
        public string $availableToSpendGel,
        public ?string $eurGelRate,
        public ?string $usdtGelRate,
        public ?string $ratesUpdatedAtLabel,
    ) {
    }
}
