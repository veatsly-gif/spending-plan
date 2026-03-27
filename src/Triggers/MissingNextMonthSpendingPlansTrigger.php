<?php

declare(strict_types=1);

namespace App\Triggers;

use App\Entity\User;
use App\Repository\SpendingPlanRepository;
use App\Service\SpendingPlanSuggestionCacheService;

final class MissingNextMonthSpendingPlansTrigger implements NotificationTriggerInterface
{
    public function __construct(
        private readonly SpendingPlanRepository $spendingPlanRepository,
        private readonly SpendingPlanSuggestionCacheService $suggestionCacheService,
    ) {
    }

    public function getCode(): string
    {
        return 'missing_next_month_spending_plans';
    }

    /**
     * @return array{
     *     monthKey: string,
     *     monthLabel: string
     * }|null
     */
    public function evaluate(User $admin, \DateTimeImmutable $now): ?array
    {
        if (!in_array('ROLE_ADMIN', $admin->getRoles(), true)) {
            return null;
        }

        $nextMonthStart = $now->modify('first day of next month')->setTime(0, 0);
        $nextMonthEnd = $nextMonthStart->modify('last day of this month')->setTime(0, 0);
        $nextMonthKey = $nextMonthStart->format('Y-m');

        if ($this->spendingPlanRepository->countForMonth($nextMonthStart, $nextMonthEnd) > 0) {
            return null;
        }

        if (!$this->suggestionCacheService->hasSuggestions($nextMonthKey)) {
            $suggestions = $this->suggestionCacheService->buildMonthSuggestions($nextMonthStart);
            $this->suggestionCacheService->storeSuggestions($nextMonthKey, $suggestions);
        }

        return [
            'monthKey' => $nextMonthKey,
            'monthLabel' => $nextMonthStart->format('F Y'),
        ];
    }
}
