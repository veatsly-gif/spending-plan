<?php

declare(strict_types=1);

namespace App\DTO\Controller\Web;

final readonly class DashboardSpendListPageDto
{
    /**
     * @param list<DashboardMonthTabDto> $monthTabs
     * @param list<DashboardSpendItemDto> $spends
     * @param list<array{
     *     id: int,
     *     name: string,
     *     totalAmountLabel: string,
     *     plannedAmountLabel: string,
     *     current: bool,
     *     expanded: bool,
     *     spends: list<DashboardSpendItemDto>
     * }> $streamGroups
     * @param list<string> $availableCurrencies
     * @param list<string> $availableUsers
     * @param list<array{id: int, label: string}> $availablePlans
     */
    public function __construct(
        public string $monthLabel,
        public string $selectedMonthKey,
        public string $previousMonthKey,
        public string $nextMonthKey,
        public string $viewMode,
        public array $monthTabs,
        public array $spends,
        public array $streamGroups,
        public int $totalRecords,
        public string $totalAmountLabel,
        public string $sort,
        public string $dir,
        public int $page,
        public int $perPage,
        public int $totalPages,
        public string $filterCurrency,
        public string $filterUser,
        public string $filterPlanId,
        public string $filterQuery,
        public array $availableCurrencies,
        public array $availableUsers,
        public array $availablePlans,
        public array $perPageOptions,
    ) {
    }

    /**
     * @return array{
     *     monthLabel: string,
     *     selectedMonthKey: string,
     *     previousMonthKey: string,
     *     nextMonthKey: string,
     *     viewMode: string,
     *     monthTabs: list<array{monthKey: string, label: string, active: bool}>,
     *     spends: list<DashboardSpendItemDto>,
     *     streamGroups: list<array{
     *         id: int,
     *         name: string,
     *         totalAmountLabel: string,
     *         plannedAmountLabel: string,
     *         current: bool,
     *         expanded: bool,
     *         spends: list<DashboardSpendItemDto>
     *     }>,
     *     totalRecords: int,
     *     totalAmountLabel: string,
     *     sort: string,
     *     dir: string,
     *     page: int,
     *     perPage: int,
     *     totalPages: int,
     *     filterCurrency: string,
     *     filterUser: string,
     *     filterPlanId: string,
     *     filterQuery: string,
     *     availableCurrencies: list<string>,
     *     availableUsers: list<string>,
     *     availablePlans: list<array{id: int, label: string}>,
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
            'viewMode' => $this->viewMode,
            'monthTabs' => $monthTabs,
            'spends' => $this->spends,
            'streamGroups' => $this->streamGroups,
            'totalRecords' => $this->totalRecords,
            'totalAmountLabel' => $this->totalAmountLabel,
            'sort' => $this->sort,
            'dir' => $this->dir,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'totalPages' => $this->totalPages,
            'filterCurrency' => $this->filterCurrency,
            'filterUser' => $this->filterUser,
            'filterPlanId' => $this->filterPlanId,
            'filterQuery' => $this->filterQuery,
            'availableCurrencies' => $this->availableCurrencies,
            'availableUsers' => $this->availableUsers,
            'availablePlans' => $this->availablePlans,
            'perPageOptions' => $this->perPageOptions,
        ];
    }
}
