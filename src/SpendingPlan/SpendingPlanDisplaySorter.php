<?php

declare(strict_types=1);

namespace App\SpendingPlan;

use App\Entity\SpendingPlan;

/**
 * Shared ordering for spending-plan lists (admin month view, add-spend select).
 *
 * Rule set:
 * 1. Active boosted plans (weight > 1) first.
 * 2. Active date-based plans (weekday/weekend/custom) second.
 * 3. Then future date-based, regular, planned, past date-based.
 */
final class SpendingPlanDisplaySorter
{
    /**
     * @param list<SpendingPlan> $plans
     * @param \DateTimeImmutable|null $referenceDate Day used to classify current/future/past date-based plans.
     *
     * @return list<SpendingPlan>
     */
    public static function sort(array $plans, ?\DateTimeImmutable $referenceDate = null): array
    {
        $day = ($referenceDate ?? new \DateTimeImmutable())->setTime(0, 0);

        usort($plans, static function (SpendingPlan $a, SpendingPlan $b) use ($day): int {
            $aRank = self::rank($a, $day);
            $bRank = self::rank($b, $day);
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            $byPriority = self::compareInsideRank($a, $b, $day, $aRank);
            if (0 !== $byPriority) {
                return $byPriority;
            }

            $byName = strcasecmp($a->getName(), $b->getName());
            if (0 !== $byName) {
                return $byName;
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        return $plans;
    }

    private static function rank(SpendingPlan $plan, \DateTimeImmutable $day): int
    {
        $isCurrent = self::isCurrent($plan, $day);
        $isDateBased = !self::isRegularOrPlanned($plan);

        if ($plan->getWeight() > 1 && $isCurrent) {
            return 0;
        }

        if ($isDateBased && $isCurrent) {
            return 1;
        }

        if ($isDateBased && $plan->getDateFrom() > $day) {
            return 2;
        }

        if (SpendingPlan::PLAN_TYPE_REGULAR === $plan->getPlanType()) {
            return 3;
        }

        if (SpendingPlan::PLAN_TYPE_PLANNED === $plan->getPlanType()) {
            return 4;
        }

        if ($isDateBased && $plan->getDateTo() < $day) {
            return 5;
        }

        return 6;
    }

    private static function compareInsideRank(
        SpendingPlan $a,
        SpendingPlan $b,
        \DateTimeImmutable $day,
        int $rank,
    ): int {
        if (0 === $rank || 1 === $rank || 2 === $rank || 3 === $rank || 6 === $rank) {
            $byWeight = $b->getWeight() <=> $a->getWeight();
            if (0 !== $byWeight) {
                return $byWeight;
            }

            $byFrom = $a->getDateFrom() <=> $b->getDateFrom();
            if (0 !== $byFrom) {
                return $byFrom;
            }

            return $a->getDateTo() <=> $b->getDateTo();
        }

        if (4 === $rank) {
            $byFrom = $a->getDateFrom() <=> $b->getDateFrom();
            if (0 !== $byFrom) {
                return $byFrom;
            }

            $byWeight = $b->getWeight() <=> $a->getWeight();
            if (0 !== $byWeight) {
                return $byWeight;
            }

            return $a->getDateTo() <=> $b->getDateTo();
        }

        if (5 === $rank) {
            $byTo = $b->getDateTo() <=> $a->getDateTo();
            if (0 !== $byTo) {
                return $byTo;
            }

            $byWeight = $b->getWeight() <=> $a->getWeight();
            if (0 !== $byWeight) {
                return $byWeight;
            }

            return $a->getDateFrom() <=> $b->getDateFrom();
        }

        return 0;
    }

    private static function isRegularOrPlanned(SpendingPlan $plan): bool
    {
        return $plan->isDateBasedLimitPlanType();
    }

    private static function isCurrent(SpendingPlan $plan, \DateTimeImmutable $day): bool
    {
        return $plan->getDateFrom() <= $day && $plan->getDateTo() >= $day;
    }
}
