<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Controller\Admin\AdminSpendingPlanSuggestionDto;
use App\Entity\SpendingPlan;
use App\Redis\RedisDataKey;

final class SpendingPlanSuggestionCacheService
{
    public function __construct(
        private readonly RedisStore $redisStore,
    ) {
    }

    public function hasSuggestions(string $monthKey): bool
    {
        return null !== $this->redisStore->getByDataKey(
            RedisDataKey::SPENDING_PLAN_SUGGESTIONS,
            ['monthKey' => $monthKey]
        );
    }

    /**
     * @param list<AdminSpendingPlanSuggestionDto> $suggestions
     */
    public function storeSuggestions(string $monthKey, array $suggestions): void
    {
        $payload = [];
        foreach ($suggestions as $suggestion) {
            $payload[] = $suggestion->toArray();
        }

        $this->redisStore->setJsonByDataKey(
            RedisDataKey::SPENDING_PLAN_SUGGESTIONS,
            ['monthKey' => $monthKey],
            $payload
        );
    }

    /**
     * @return list<AdminSpendingPlanSuggestionDto>
     */
    public function getSuggestions(string $monthKey): array
    {
        $decoded = $this->redisStore->getJsonByDataKey(
            RedisDataKey::SPENDING_PLAN_SUGGESTIONS,
            ['monthKey' => $monthKey]
        );
        if (null === $decoded) {
            return [];
        }

        $result = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            /** @var array{
             *     id: string,
             *     name: string,
             *     planType: string,
             *     dateFrom: string,
             *     dateTo: string,
             *     limitAmount: string,
             *     currency: string,
             *     weight: int,
             *     note: ?string
             * } $row
             */
            $result[] = AdminSpendingPlanSuggestionDto::fromArray($row);
        }

        return $result;
    }

    public function clearSuggestions(string $monthKey): void
    {
        $this->redisStore->deleteByDataKey(
            RedisDataKey::SPENDING_PLAN_SUGGESTIONS,
            ['monthKey' => $monthKey]
        );
    }

    /**
     * @return array{removed: bool, suggestion: ?AdminSpendingPlanSuggestionDto}
     */
    public function removeSuggestion(string $monthKey, string $suggestionId): array
    {
        $suggestions = $this->getSuggestions($monthKey);
        $kept = [];
        $removed = null;

        foreach ($suggestions as $suggestion) {
            if ($suggestion->id === $suggestionId) {
                $removed = $suggestion;
                continue;
            }

            $kept[] = $suggestion;
        }

        $this->storeSuggestions($monthKey, $kept);

        return [
            'removed' => null !== $removed,
            'suggestion' => $removed,
        ];
    }

    /**
     * @return list<AdminSpendingPlanSuggestionDto>
     */
    public function buildMonthSuggestions(\DateTimeImmutable $monthStart): array
    {
        $monthEnd = $monthStart->modify('last day of this month')->setTime(0, 0);
        $result = [];

        $cursor = $monthStart;
        while ($cursor <= $monthEnd) {
            $isWeekend = in_array((int) $cursor->format('N'), [6, 7], true);
            $type = $isWeekend ? SpendingPlan::PLAN_TYPE_WEEKEND : SpendingPlan::PLAN_TYPE_WEEKDAY;
            $daily = $isWeekend ? 250.0 : 100.0;

            $groupStart = $cursor;
            $groupEnd = $cursor;
            $sum = $daily;

            $next = $cursor->modify('+1 day');
            while ($next <= $monthEnd) {
                $nextIsWeekend = in_array((int) $next->format('N'), [6, 7], true);
                $nextType = $nextIsWeekend ? SpendingPlan::PLAN_TYPE_WEEKEND : SpendingPlan::PLAN_TYPE_WEEKDAY;
                if ($nextType !== $type) {
                    break;
                }

                $groupEnd = $next;
                $sum += $nextIsWeekend ? 250.0 : 100.0;
                $next = $next->modify('+1 day');
            }

            $rangeSuffix = (int) $groupStart->format('j') === (int) $groupEnd->format('j')
                ? $groupStart->format('j')
                : sprintf('%s-%s', $groupStart->format('j'), $groupEnd->format('j'));
            $namePrefix = SpendingPlan::PLAN_TYPE_WEEKEND === $type ? 'Weekends' : 'Weekdays';
            $id = sprintf(
                '%s-%s-%s',
                $type,
                $groupStart->format('Ymd'),
                $groupEnd->format('Ymd')
            );

            $result[] = new AdminSpendingPlanSuggestionDto(
                $id,
                sprintf('%s %s', $namePrefix, $rangeSuffix),
                $type,
                $groupStart->format('Y-m-d'),
                $groupEnd->format('Y-m-d'),
                number_format($sum, 2, '.', ''),
                'GEL',
                1,
                'Auto-suggested by monthly defaults.',
            );

            $cursor = $groupEnd->modify('+1 day');
        }

        $result[] = new AdminSpendingPlanSuggestionDto(
            sprintf('regular-%s', $monthStart->format('Ym')),
            'Regular spends',
            SpendingPlan::PLAN_TYPE_REGULAR,
            $monthStart->format('Y-m-d'),
            $monthEnd->format('Y-m-d'),
            '0.00',
            'GEL',
            0,
            'Default monthly category for recurring payments.',
        );

        $result[] = new AdminSpendingPlanSuggestionDto(
            sprintf('planned-%s', $monthStart->format('Ym')),
            'Planned spends',
            SpendingPlan::PLAN_TYPE_PLANNED,
            $monthStart->format('Y-m-d'),
            $monthEnd->format('Y-m-d'),
            '0.00',
            'GEL',
            0,
            'Default monthly category for planned purchases.',
        );

        return $result;
    }

}
