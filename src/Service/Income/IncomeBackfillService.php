<?php

declare(strict_types=1);

namespace App\Service\Income;

use App\Repository\IncomeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class IncomeBackfillService
{
    public function __construct(
        private readonly IncomeRepository $incomeRepository,
        private readonly IncomeRateService $incomeRateService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function backfillOlderThanOneDay(\DateTimeImmutable $now): int
    {
        $threshold = $now->modify('-1 day');
        $incomes = $this->incomeRepository->findWithoutOfficialRatedAmountOlderThan($threshold);
        if ([] === $incomes) {
            return 0;
        }

        $byDay = [];
        foreach ($incomes as $income) {
            $currency = $income->getCurrency();
            if (null === $currency) {
                continue;
            }

            $day = $income->getCreatedAt()->format('Y-m-d');
            if (!isset($byDay[$day])) {
                $byDay[$day] = [
                    'date' => new \DateTimeImmutable($day),
                    'codes' => [],
                ];
            }

            $byDay[$day]['codes'][$currency->getCode()] = true;
        }

        $ratesByDay = [];
        foreach ($byDay as $day => $payload) {
            $codes = array_keys($payload['codes']);
            $ratesByDay[$day] = $this->incomeRateService->getOfficialGelRatesForDate(
                $payload['date'],
                $codes
            );
        }

        $updatedCount = 0;
        foreach ($incomes as $income) {
            $currency = $income->getCurrency();
            if (null === $currency) {
                continue;
            }

            $day = $income->getCreatedAt()->format('Y-m-d');
            $rate = $ratesByDay[$day][$currency->getCode()] ?? null;
            if (null === $rate) {
                $this->logger->warning('Income official GEL backfill skipped: rate is unavailable.', [
                    'income_id' => $income->getId(),
                    'currency' => $currency->getCode(),
                    'date' => $day,
                ]);
                continue;
            }

            $officialAmount = number_format(
                ((float) $income->getAmount()) * ((float) $rate),
                4,
                '.',
                ''
            );
            $income->setOfficialRatedAmountInGel($officialAmount);
            $updatedCount++;
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        return $updatedCount;
    }
}
