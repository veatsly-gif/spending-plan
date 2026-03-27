<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class DashboardIncomeListPageDto
{
    /**
     * @param list<DashboardIncomeItemDto> $incomes
     */
    public function __construct(
        public string $monthLabel,
        public array $incomes,
        public string $totalAmountInGel,
        public string $totalOfficialRatedAmountInGel,
    ) {
    }

    /**
     * @return array{
     *     monthLabel: string,
     *     incomes: list<DashboardIncomeItemDto>,
     *     totalAmountInGel: string,
     *     totalOfficialRatedAmountInGel: string
     * }
     */
    public function toArray(): array
    {
        return [
            'monthLabel' => $this->monthLabel,
            'incomes' => $this->incomes,
            'totalAmountInGel' => $this->totalAmountInGel,
            'totalOfficialRatedAmountInGel' => $this->totalOfficialRatedAmountInGel,
        ];
    }
}
