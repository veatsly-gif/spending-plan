<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class DashboardSpendWidgetDto
{
    public function __construct(
        public string $monthLabel,
        public ?string $currentTimePlanName,
        public string $currentTimePlanSpentGel,
        public string $currentTimePlanLimitGel,
        public int $currentTimePlanProgressPercent,
        public int $currentTimePlanProgressBarPercent,
        public string $currentTimePlanProgressTone,
        public string $monthSpentGel,
        public string $monthLimitGel,
        public int $monthSpendProgressPercent,
        public int $monthSpendProgressBarPercent,
        public string $monthSpendProgressTone,
        public string $todaySpentGel,
        /** @var list<DashboardSpendItemDto> */
        public array $recentSpends,
    ) {
    }
}
