<?php

declare(strict_types=1);

namespace App\Service\Income;

use App\Repository\IncomeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IncomeLiveGelFillService
{
    public function __construct(
        private readonly IncomeRepository $incomeRepository,
        private readonly IncomeRateService $incomeRateService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function fillMissingAmountInGel(): int
    {
        $incomes = $this->incomeRepository->findWithoutAmountInGel();
        if ([] === $incomes) {
            return 0;
        }

        $rates = $this->incomeRateService->getLiveGelRates();
        if (null === $rates) {
            return 0;
        }

        $updatedCount = 0;
        foreach ($incomes as $income) {
            $currency = $income->getCurrency();
            if (null === $currency) {
                continue;
            }

            $code = strtoupper($currency->getCode());
            $rate = $rates[$code] ?? null;
            if (null === $rate) {
                continue;
            }

            $amountInGel = number_format(
                ((float) $income->getAmount()) * ((float) $rate),
                2,
                '.',
                ''
            );
            $income->setAmountInGel($amountInGel);

            if (null === $income->getRate()) {
                $income->setRate($rate);
            }

            $updatedCount++;
        }

        if ($updatedCount > 0) {
            $this->entityManager->flush();
        }

        return $updatedCount;
    }
}
