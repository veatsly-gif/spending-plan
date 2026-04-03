<?php

declare(strict_types=1);

namespace App\SpendingPlan;

use App\Entity\SpendingPlan;

/**
 * Shared ordering for spending-plan lists (admin month view, add-spend select).
 * Higher {@see SpendingPlan::getWeight()} first; on tie, regular/planned (date-based limits) before other types;
 * then {@see SpendingPlan::getDateFrom()}, then id.
 */
final class SpendingPlanDisplaySorter
{
    /**
     * @param list<SpendingPlan> $plans
     *
     * @return list<SpendingPlan>
     */
    public static function sort(array $plans): array
    {
        usort($plans, static function (SpendingPlan $a, SpendingPlan $b): int {
            $byWeight = $b->getWeight() <=> $a->getWeight();
            if (0 !== $byWeight) {
                return $byWeight;
            }

            $aDateBased = $a->isDateBasedLimitPlanType();
            $bDateBased = $b->isDateBasedLimitPlanType();
            $byDateBasedType = ($aDateBased ? 0 : 1) <=> ($bDateBased ? 0 : 1);
            if (0 !== $byDateBasedType) {
                return $byDateBasedType;
            }

            $byFrom = $a->getDateFrom() <=> $b->getDateFrom();
            if (0 !== $byFrom) {
                return $byFrom;
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        return $plans;
    }
}
