<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class DashboardPageViewDto
{
    public function __construct(
        public bool $isIncomer,
        public DashboardIncomeWidgetDto $incomeWidget,
        public DashboardSpendWidgetDto $spendWidget,
    ) {
    }

    /**
     * @return array{
     *     isIncomer: bool,
     *     incomeWidget: DashboardIncomeWidgetDto,
     *     spendWidget: DashboardSpendWidgetDto
     * }
     */
    public function toArray(): array
    {
        return [
            'isIncomer' => $this->isIncomer,
            'incomeWidget' => $this->incomeWidget,
            'spendWidget' => $this->spendWidget,
        ];
    }
}
