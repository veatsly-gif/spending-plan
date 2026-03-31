<?php

declare(strict_types=1);

namespace App\DTO\Cache;

final readonly class MonthlyBalanceSnapshotDto
{
    public function __construct(
        public string $monthKey,
        public string $monthLabel,
        public string $totalIncomeGel,
        public string $regularAndPlannedGel,
        public string $availableToSpendGel,
        public string $monthSpentGel,
        public string $monthLimitGel,
        public int $monthSpendProgressPercent,
        public int $monthSpendProgressBarPercent,
        public string $monthSpendProgressTone,
        public string $todaySpentGel,
        public string $todayDate,
        public string $refreshedAt,
    ) {
    }

    /**
     * @return array{
     *     monthKey: string,
     *     monthLabel: string,
     *     totalIncomeGel: string,
     *     regularAndPlannedGel: string,
     *     availableToSpendGel: string,
     *     monthSpentGel: string,
     *     monthLimitGel: string,
     *     monthSpendProgressPercent: int,
     *     monthSpendProgressBarPercent: int,
     *     monthSpendProgressTone: string,
     *     todaySpentGel: string,
     *     todayDate: string,
     *     refreshedAt: string
     * }
     */
    public function toArray(): array
    {
        return [
            'monthKey' => $this->monthKey,
            'monthLabel' => $this->monthLabel,
            'totalIncomeGel' => $this->totalIncomeGel,
            'regularAndPlannedGel' => $this->regularAndPlannedGel,
            'availableToSpendGel' => $this->availableToSpendGel,
            'monthSpentGel' => $this->monthSpentGel,
            'monthLimitGel' => $this->monthLimitGel,
            'monthSpendProgressPercent' => $this->monthSpendProgressPercent,
            'monthSpendProgressBarPercent' => $this->monthSpendProgressBarPercent,
            'monthSpendProgressTone' => $this->monthSpendProgressTone,
            'todaySpentGel' => $this->todaySpentGel,
            'todayDate' => $this->todayDate,
            'refreshedAt' => $this->refreshedAt,
        ];
    }

    public static function fromArray(array $payload): ?self
    {
        $monthKey = trim((string) ($payload['monthKey'] ?? ''));
        if (1 !== preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            return null;
        }

        $progressPercent = (int) ($payload['monthSpendProgressPercent'] ?? 0);
        $progressBarPercent = (int) ($payload['monthSpendProgressBarPercent'] ?? 0);
        $tone = trim((string) ($payload['monthSpendProgressTone'] ?? 'ok'));
        if (!in_array($tone, ['ok', 'warning', 'danger'], true)) {
            $tone = 'ok';
        }

        return new self(
            $monthKey,
            (string) ($payload['monthLabel'] ?? ''),
            (string) ($payload['totalIncomeGel'] ?? '0.00'),
            (string) ($payload['regularAndPlannedGel'] ?? '0.00'),
            (string) ($payload['availableToSpendGel'] ?? '0.00'),
            (string) ($payload['monthSpentGel'] ?? '0.00'),
            (string) ($payload['monthLimitGel'] ?? '0.00'),
            max(0, $progressPercent),
            max(0, min(100, $progressBarPercent)),
            $tone,
            (string) ($payload['todaySpentGel'] ?? '0.00'),
            (string) ($payload['todayDate'] ?? ''),
            (string) ($payload['refreshedAt'] ?? ''),
        );
    }
}
