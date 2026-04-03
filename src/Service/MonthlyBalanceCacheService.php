<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Cache\MonthlyBalanceSnapshotDto;
use App\Entity\SpendingPlan;
use App\Redis\RedisDataKey;
use App\Repository\IncomeRepository;
use App\Repository\SpendRepository;
use App\Repository\SpendingPlanRepository;
use App\Service\Income\IncomeRateService;
use App\Util\RussianCalendarFormatter;

final class MonthlyBalanceCacheService
{
    private const MAX_CACHE_AGE_SECONDS = 300;

    public function __construct(
        private readonly RedisStore $redisStore,
        private readonly IncomeRepository $incomeRepository,
        private readonly SpendRepository $spendRepository,
        private readonly SpendingPlanRepository $spendingPlanRepository,
        private readonly IncomeRateService $incomeRateService,
    ) {
    }

    public function getOrRefresh(
        \DateTimeImmutable $now,
    ): MonthlyBalanceSnapshotDto {
        return $this->getOrRefreshByMonthKey($now->format('Y-m'), $now);
    }

    public function getOrRefreshByMonthKey(
        string $monthKey,
        \DateTimeImmutable $now,
    ): MonthlyBalanceSnapshotDto {
        $resolvedMonthKey = $this->sanitizeMonthKey($monthKey) ?? $now->format('Y-m');

        $cached = $this->loadSnapshot($resolvedMonthKey);
        if (
            null !== $cached
            && !$this->isStaleTodaySnapshot($cached, $resolvedMonthKey, $now)
            && !$this->isExpiredSnapshot($cached, $now)
        ) {
            return $cached;
        }

        return $this->refreshByMonthKey($resolvedMonthKey, $now);
    }

    public function refreshByMonthKey(
        string $monthKey,
        \DateTimeImmutable $now,
    ): MonthlyBalanceSnapshotDto {
        $resolvedMonthKey = $this->sanitizeMonthKey($monthKey) ?? $now->format('Y-m');
        $monthStart = $this->monthStart($resolvedMonthKey);
        $monthEnd = $monthStart->modify('last day of this month')->setTime(0, 0);

        $monthIncomes = $this->incomeRepository->findForMonth($monthStart);
        $monthSpends = $this->spendRepository->findForMonth($monthStart);
        $monthPlans = $this->spendingPlanRepository->findForMonth($monthStart, $monthEnd);
        $liveRates = $this->incomeRateService->getLiveGelRates() ?? [];

        $today = $now->setTime(0, 0);
        $isCurrentMonth = $resolvedMonthKey === $today->format('Y-m');

        $totalIncomeGel = 0.0;
        foreach ($monthIncomes as $income) {
            $converted = $this->convertToGel((float) $income->getAmount(), (string) $income->getCurrency()?->getCode(), $liveRates);
            if (null !== $converted) {
                $totalIncomeGel += $converted;
            }
        }

        $monthSpentGel = 0.0;
        $todaySpentGel = 0.0;
        foreach ($monthSpends as $spend) {
            $converted = $this->convertToGel((float) $spend->getAmount(), (string) $spend->getCurrency()?->getCode(), $liveRates);
            if (null === $converted) {
                continue;
            }

            $monthSpentGel += $converted;
            if ($isCurrentMonth && $spend->getSpendDate() == $today) {
                $todaySpentGel += $converted;
            }
        }

        $monthLimitGel = 0.0;
        $regularAndPlannedGel = 0.0;
        foreach ($monthPlans as $plan) {
            $converted = $this->convertToGel((float) $plan->getLimitAmount(), (string) $plan->getCurrency()?->getCode(), $liveRates);
            if (null === $converted) {
                continue;
            }

            $monthLimitGel += $converted;
            if (in_array($plan->getPlanType(), [SpendingPlan::PLAN_TYPE_REGULAR, SpendingPlan::PLAN_TYPE_PLANNED], true)) {
                $regularAndPlannedGel += $converted;
            }
        }

        $progressPercent = 0;
        if ($monthLimitGel > 0) {
            $progressPercent = (int) round(($monthSpentGel / $monthLimitGel) * 100);
        } elseif ($monthSpentGel > 0) {
            $progressPercent = 100;
        }

        $progressBarPercent = max(0, min(100, $progressPercent));
        $progressTone = 'ok';
        if ($progressPercent > 90) {
            $progressTone = 'danger';
        } elseif ($progressPercent > 80) {
            $progressTone = 'warning';
        }

        $snapshot = new MonthlyBalanceSnapshotDto(
            $resolvedMonthKey,
            RussianCalendarFormatter::monthYear($monthStart),
            number_format($totalIncomeGel, 2, '.', ''),
            number_format($regularAndPlannedGel, 2, '.', ''),
            number_format($totalIncomeGel - $regularAndPlannedGel, 2, '.', ''),
            number_format($monthSpentGel, 2, '.', ''),
            number_format($monthLimitGel, 2, '.', ''),
            $progressPercent,
            $progressBarPercent,
            $progressTone,
            number_format($isCurrentMonth ? $todaySpentGel : 0.0, 2, '.', ''),
            $today->format('Y-m-d'),
            $now->format(\DateTimeInterface::ATOM),
        );

        $this->redisStore->setJsonByDataKey(
            RedisDataKey::MONTHLY_BALANCE_SNAPSHOT,
            ['monthKey' => $resolvedMonthKey],
            $snapshot->toArray(),
            $this->ttlToSnapshotExpiry($resolvedMonthKey, $now)
        );

        return $snapshot;
    }

    private function loadSnapshot(string $monthKey): ?MonthlyBalanceSnapshotDto
    {
        $payload = $this->redisStore->getJsonByDataKey(
            RedisDataKey::MONTHLY_BALANCE_SNAPSHOT,
            ['monthKey' => $monthKey]
        );
        if (null === $payload) {
            return null;
        }

        return MonthlyBalanceSnapshotDto::fromArray($payload);
    }

    private function isStaleTodaySnapshot(
        MonthlyBalanceSnapshotDto $snapshot,
        string $monthKey,
        \DateTimeImmutable $now,
    ): bool {
        if ($monthKey !== $now->format('Y-m')) {
            return false;
        }

        return $snapshot->todayDate !== $now->format('Y-m-d');
    }

    private function isExpiredSnapshot(MonthlyBalanceSnapshotDto $snapshot, \DateTimeImmutable $now): bool
    {
        $refreshedAtRaw = trim($snapshot->refreshedAt);
        if ('' === $refreshedAtRaw) {
            return true;
        }

        try {
            $refreshedAt = new \DateTimeImmutable($refreshedAtRaw);
        } catch (\Throwable) {
            return true;
        }

        return ($now->getTimestamp() - $refreshedAt->getTimestamp()) > self::MAX_CACHE_AGE_SECONDS;
    }

    /**
     * @param array<string, string> $liveRates
     */
    private function convertToGel(float $amount, string $currencyCode, array $liveRates): ?float
    {
        $code = strtoupper(trim($currencyCode));
        if ('' === $code) {
            return null;
        }

        if ('GEL' === $code) {
            return $amount;
        }

        $rate = $liveRates[$code] ?? null;
        if (null === $rate || !is_numeric($rate)) {
            return null;
        }

        return $amount * (float) $rate;
    }

    private function sanitizeMonthKey(?string $monthKey): ?string
    {
        if (null === $monthKey) {
            return null;
        }

        $normalized = trim($monthKey);
        if (1 !== preg_match('/^\d{4}-\d{2}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    private function monthStart(string $monthKey): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $monthKey.'-01 00:00:00')
            ?: new \DateTimeImmutable('first day of this month');
    }

    private function ttlToSnapshotExpiry(string $monthKey, \DateTimeImmutable $now): int
    {
        $monthStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $monthKey.'-01 00:00:00');
        if (!$monthStart instanceof \DateTimeImmutable) {
            return 86400;
        }

        $expiresAt = $monthStart
            ->modify('last day of this month')
            ->setTime(23, 59, 59)
            ->modify('+120 days');

        return max(3600, $expiresAt->getTimestamp() - $now->getTimestamp());
    }
}
