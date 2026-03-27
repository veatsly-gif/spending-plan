<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

use App\Entity\SpendingPlan;

final readonly class AdminSpendingPlansIndexViewDto
{
    /**
     * @param list<AdminSpendingPlanMonthTabDto> $monthTabs
     * @param list<AdminSpendingPlanSuggestionDto> $suggestedPlans
     * @param list<SpendingPlan> $existingPlans
     */
    public function __construct(
        public array $monthTabs,
        public string $selectedMonthKey,
        public string $selectedMonthLabel,
        public array $availableCurrencies,
        public array $suggestedPlans,
        public array $existingPlans,
        public AdminSpendingPlanPopupDto $popup,
    ) {
    }

    /**
     * @return array{
     *     monthTabs: list<array{
     *         monthKey: string,
     *         label: string,
     *         active: bool,
     *         needsAttention: bool
     *     }>,
     *     selectedMonthKey: string,
     *     selectedMonthLabel: string,
     *     availableCurrencies: list<string>,
     *     suggestedPlans: list<array{
     *         id: string,
     *         name: string,
     *         planType: string,
     *         dateFrom: string,
     *         dateTo: string,
     *         limitAmount: string,
     *         currency: string,
     *         weight: int,
     *         note: ?string
     *     }>,
     *     existingPlans: list<SpendingPlan>,
     *     popup: array{
     *         show: bool,
     *         title: string,
     *         message: string,
     *         monthKey: string
     *     }
     * }
     */
    public function toArray(): array
    {
        $monthTabs = [];
        foreach ($this->monthTabs as $tab) {
            $monthTabs[] = $tab->toArray();
        }

        $suggestedPlans = [];
        foreach ($this->suggestedPlans as $plan) {
            $suggestedPlans[] = $plan->toArray();
        }

        return [
            'monthTabs' => $monthTabs,
            'selectedMonthKey' => $this->selectedMonthKey,
            'selectedMonthLabel' => $this->selectedMonthLabel,
            'availableCurrencies' => $this->availableCurrencies,
            'suggestedPlans' => $suggestedPlans,
            'existingPlans' => $this->existingPlans,
            'popup' => $this->popup->toArray(),
        ];
    }
}
