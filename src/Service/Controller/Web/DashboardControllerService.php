<?php

declare(strict_types=1);

namespace App\Service\Controller\Web;

use App\DTO\Controller\Web\DashboardIncomeDraftDto;
use App\DTO\Controller\Web\DashboardIncomeItemDto;
use App\DTO\Controller\Web\DashboardIncomeListPageDto;
use App\DTO\Controller\Web\DashboardIncomeWidgetDto;
use App\DTO\Controller\Web\DashboardPageViewDto;
use App\DTO\Controller\Web\IncomeCreateResultDto;
use App\Entity\Income;
use App\Entity\User;
use App\Repository\CurrencyRepository;
use App\Repository\IncomeRepository;
use App\Service\Income\IncomeRateService;

final class DashboardControllerService
{
    public function __construct(
        private readonly CurrencyRepository $currencyRepository,
        private readonly IncomeRepository $incomeRepository,
        private readonly IncomeRateService $incomeRateService,
    ) {
    }

    public function buildViewData(User $user, \DateTimeImmutable $now): DashboardPageViewDto
    {
        $isIncomer = $this->hasRole($user, 'ROLE_INCOMER');
        $monthStart = $now->modify('first day of this month')->setTime(0, 0);
        $monthIncomes = $this->incomeRepository->findForMonth($monthStart);
        $lastIncome = $this->incomeRepository->findLast();
        $rates = $this->incomeRateService->getLiveRates();

        $totalGel = 0.0;
        foreach ($monthIncomes as $income) {
            if (null !== $income->getAmountInGel()) {
                $totalGel += (float) $income->getAmountInGel();
            }
        }

        return new DashboardPageViewDto(
            $isIncomer,
            new DashboardIncomeWidgetDto(
                null !== $lastIncome ? $this->mapIncome($lastIncome) : null,
                $monthStart->format('F Y'),
                count($monthIncomes),
                number_format($totalGel, 2, '.', ''),
                $rates?->eurGel,
                $rates?->usdtGel,
                null !== $rates ? $rates->updatedAt->format('Y-m-d H:i') : null,
            )
        );
    }

    public function buildIncomeListViewData(\DateTimeImmutable $now): DashboardIncomeListPageDto
    {
        $monthStart = $now->modify('first day of this month')->setTime(0, 0);
        $incomes = $this->incomeRepository->findForMonth($monthStart);
        $items = [];
        $totalAmountInGel = 0.0;
        $totalOfficialRatedAmountInGel = 0.0;
        foreach ($incomes as $income) {
            $items[] = $this->mapIncome($income);
            if (null !== $income->getAmountInGel()) {
                $totalAmountInGel += (float) $income->getAmountInGel();
            }

            if (null !== $income->getOfficialRatedAmountInGel()) {
                $totalOfficialRatedAmountInGel += (float) $income->getOfficialRatedAmountInGel();
            }
        }

        return new DashboardIncomeListPageDto(
            $monthStart->format('F Y'),
            $items,
            number_format($totalAmountInGel, 2, '.', ''),
            number_format($totalOfficialRatedAmountInGel, 4, '.', '')
        );
    }

    public function createIncomeDraft(): DashboardIncomeDraftDto
    {
        $draft = new DashboardIncomeDraftDto();
        $draft->setCurrency($this->currencyRepository->findOneByCode('GEL'));

        return $draft;
    }

    public function createIncome(User $user, DashboardIncomeDraftDto $draft): IncomeCreateResultDto
    {
        if (!$this->hasRole($user, 'ROLE_INCOMER')) {
            return new IncomeCreateResultDto(false, 'Income creation is allowed for incomer role only.');
        }

        $amount = $draft->getAmount();
        if (!is_numeric($amount) || (float) $amount < 0) {
            return new IncomeCreateResultDto(false, 'Amount must be a positive number.');
        }

        $currency = $draft->getCurrency();
        if (null === $currency) {
            return new IncomeCreateResultDto(false, 'Currency is required.');
        }

        $income = (new Income())
            ->setUserAdded($user)
            ->setAmount(number_format((float) $amount, 2, '.', ''))
            ->setCurrency($currency)
            ->setComment($draft->getComment());

        if ($draft->isConvertToGel()) {
            $rate = $this->incomeRateService->getLiveGelRateForCurrency($currency->getCode());
            if (null === $rate) {
                return new IncomeCreateResultDto(
                    false,
                    'Unable to convert now. Refresh rates first or uncheck conversion.'
                );
            }

            $converted = $this->incomeRateService->convertAmountToGel(
                $income->getAmount(),
                $currency->getCode()
            );
            if (null === $converted) {
                return new IncomeCreateResultDto(
                    false,
                    'Unable to convert now. Refresh rates first or uncheck conversion.'
                );
            }

            $income->setAmountInGel($converted);
            $income->setRate($rate);
        }

        $this->incomeRepository->save($income, true);

        return new IncomeCreateResultDto(true);
    }

    private function hasRole(User $user, string $role): bool
    {
        return in_array($role, $user->getRoles(), true);
    }

    private function mapIncome(Income $income): DashboardIncomeItemDto
    {
        return new DashboardIncomeItemDto(
            (int) $income->getId(),
            (string) $income->getUserAdded()?->getUsername(),
            $income->getAmount(),
            (string) $income->getCurrency()?->getCode(),
            $income->getAmountInGel(),
            $income->getOfficialRatedAmountInGel(),
            $income->getRate(),
            $income->getComment(),
            $income->getCreatedAt()->format('Y-m-d H:i')
        );
    }
}
