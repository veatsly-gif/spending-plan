<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class DashboardIncomeListPageDto
{
    /**
     * @param list<DashboardMonthTabDto> $monthTabs
     * @param list<DashboardIncomeItemDto> $incomes
     * @param list<string> $availableCurrencies
     * @param list<string> $availableUsers
     * @param list<int> $perPageOptions
     */
    public function __construct(
        public string $monthLabel,
        public string $selectedMonthKey,
        public string $previousMonthKey,
        public string $nextMonthKey,
        public array $monthTabs,
        public array $incomes,
        public int $totalRecords,
        public string $totalAmountLabel,
        public string $totalAmountInGel,
        public string $totalOfficialRatedAmountInGel,
        public string $sort,
        public string $dir,
        public int $page,
        public int $perPage,
        public int $totalPages,
        public string $filterCurrency,
        public string $filterUser,
        public string $filterQuery,
        public array $availableCurrencies,
        public array $availableUsers,
        public array $perPageOptions,
    ) {
    }

    /**
     * @return array{
     *     monthLabel: string,
     *     selectedMonthKey: string,
     *     previousMonthKey: string,
     *     nextMonthKey: string,
     *     monthTabs: list<array{monthKey: string, label: string, active: bool}>,
     *     incomes: list<DashboardIncomeItemDto>,
     *     totalRecords: int,
     *     totalAmountLabel: string,
     *     totalAmountInGel: string,
     *     totalOfficialRatedAmountInGel: string,
     *     sort: string,
     *     dir: string,
     *     page: int,
     *     perPage: int,
     *     totalPages: int,
     *     filterCurrency: string,
     *     filterUser: string,
     *     filterQuery: string,
     *     availableCurrencies: list<string>,
     *     availableUsers: list<string>,
     *     perPageOptions: list<int>
     * }
     */
    public function toArray(): array
    {
        $monthTabs = [];
        foreach ($this->monthTabs as $monthTab) {
            $monthTabs[] = $monthTab->toArray();
        }

        return [
            'monthLabel' => $this->monthLabel,
            'selectedMonthKey' => $this->selectedMonthKey,
            'previousMonthKey' => $this->previousMonthKey,
            'nextMonthKey' => $this->nextMonthKey,
            'monthTabs' => $monthTabs,
            'incomes' => $this->incomes,
            'totalRecords' => $this->totalRecords,
            'totalAmountLabel' => $this->totalAmountLabel,
            'totalAmountInGel' => $this->totalAmountInGel,
            'totalOfficialRatedAmountInGel' => $this->totalOfficialRatedAmountInGel,
            'sort' => $this->sort,
            'dir' => $this->dir,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'totalPages' => $this->totalPages,
            'filterCurrency' => $this->filterCurrency,
            'filterUser' => $this->filterUser,
            'filterQuery' => $this->filterQuery,
            'availableCurrencies' => $this->availableCurrencies,
            'availableUsers' => $this->availableUsers,
            'perPageOptions' => $this->perPageOptions,
        ];
    }
}
