<?php

declare(strict_types=1);

namespace App\Service\Controller\Web;

use App\DTO\Controller\Web\DashboardIncomeDraftDto;
use App\DTO\Controller\Web\DashboardIncomeItemDto;
use App\DTO\Controller\Web\DashboardIncomeListPageDto;
use App\DTO\Controller\Web\DashboardIncomeWidgetDto;
use App\DTO\Controller\Web\DashboardMonthTabDto;
use App\DTO\Controller\Web\DashboardPageViewDto;
use App\DTO\Controller\Web\DashboardSpendDraftDto;
use App\DTO\Controller\Web\DashboardSpendItemDto;
use App\DTO\Controller\Web\DashboardSpendListPageDto;
use App\DTO\Controller\Web\DashboardSpendWidgetDto;
use App\DTO\Controller\Web\IncomeCreateResultDto;
use App\DTO\Controller\Web\SpendCreateResultDto;
use App\Event\MonthlyBalanceRefreshRequestedEvent;
use App\Entity\Income;
use App\Entity\Spend;
use App\Entity\SpendingPlan;
use App\Entity\User;
use App\Repository\CurrencyRepository;
use App\Repository\IncomeRepository;
use App\Repository\SpendRepository;
use App\Repository\SpendingPlanRepository;
use App\Service\Income\IncomeRateService;
use App\Service\MonthlyBalanceCacheService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DashboardControllerService
{
    private const SORT_SPEND_DATE = 'spendDate';
    private const SORT_CREATED_AT = 'createdAt';
    private const SORT_AMOUNT = 'amount';
    private const SORT_CURRENCY = 'currency';
    private const SORT_USERNAME = 'username';
    private const SORT_SPENDING_PLAN = 'spendingPlan';
    private const SORT_AMOUNT_IN_GEL = 'amountInGel';
    private const SORT_OFFICIAL_RATED_AMOUNT_IN_GEL = 'officialRatedAmountInGel';
    private const SORT_RATE = 'rate';

    /**
     * @var list<int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50];

    public function __construct(
        private readonly CurrencyRepository $currencyRepository,
        private readonly IncomeRepository $incomeRepository,
        private readonly SpendRepository $spendRepository,
        private readonly SpendingPlanRepository $spendingPlanRepository,
        private readonly IncomeRateService $incomeRateService,
        private readonly MonthlyBalanceCacheService $monthlyBalanceCacheService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function buildViewData(User $user, \DateTimeImmutable $now): DashboardPageViewDto
    {
        $isIncomer = $this->hasRole($user, 'ROLE_INCOMER');
        $monthStart = $now->modify('first day of this month')->setTime(0, 0);
        $balanceSnapshot = $this->monthlyBalanceCacheService->getOrRefresh($now);
        $ratesSnapshot = $this->incomeRateService->getLiveRates();
        $monthSpends = $this->spendRepository->findForMonth($monthStart);

        $recentSpends = array_slice($monthSpends, 0, 3);
        $recentSpendItems = array_map(fn (Spend $spend): DashboardSpendItemDto => $this->mapSpend($spend), $recentSpends);

        return new DashboardPageViewDto(
            $isIncomer,
            new DashboardIncomeWidgetDto(
                $monthStart->format('F Y'),
                $balanceSnapshot->totalIncomeGel,
                $balanceSnapshot->regularAndPlannedGel,
                $balanceSnapshot->availableToSpendGel,
                $ratesSnapshot?->eurGel,
                $ratesSnapshot?->usdtGel,
                null !== $ratesSnapshot ? $ratesSnapshot->updatedAt->format('Y-m-d H:i') : null,
            ),
            new DashboardSpendWidgetDto(
                $monthStart->format('F Y'),
                $balanceSnapshot->monthSpentGel,
                $balanceSnapshot->monthLimitGel,
                $balanceSnapshot->monthSpendProgressPercent,
                $balanceSnapshot->monthSpendProgressBarPercent,
                $balanceSnapshot->monthSpendProgressTone,
                $balanceSnapshot->todaySpentGel,
                $recentSpendItems,
            )
        );
    }

    public function buildIncomeListViewData(array $query, \DateTimeImmutable $now): DashboardIncomeListPageDto
    {
        $monthKey = $this->sanitizeMonthKey(isset($query['month']) ? (string) $query['month'] : null)
            ?? $now->format('Y-m');
        $monthStart = $this->monthStart($monthKey);
        $incomes = $this->incomeRepository->findForMonth($monthStart);

        $availableCurrencies = $this->extractAvailableIncomeCurrencies($incomes);
        if ([] === $availableCurrencies) {
            foreach ($this->currencyRepository->findBy([], ['code' => 'ASC']) as $currency) {
                $availableCurrencies[] = $currency->getCode();
            }
        }

        $availableUsers = $this->extractAvailableIncomeUsers($incomes);

        $filterCurrency = $this->sanitizeOptionalText($query['currency'] ?? null);
        $filterUser = $this->sanitizeOptionalText($query['user'] ?? null);
        $filterQuery = $this->sanitizeOptionalText($query['q'] ?? null);

        $filtered = [];
        foreach ($incomes as $income) {
            if ('' !== $filterCurrency && $filterCurrency !== (string) $income->getCurrency()?->getCode()) {
                continue;
            }

            if ('' !== $filterUser && $filterUser !== (string) $income->getUserAdded()?->getUsername()) {
                continue;
            }

            if ('' !== $filterQuery) {
                $needle = mb_strtolower($filterQuery);
                $comment = mb_strtolower((string) $income->getComment());
                $username = mb_strtolower((string) $income->getUserAdded()?->getUsername());
                $currency = mb_strtolower((string) $income->getCurrency()?->getCode());

                if (!str_contains($comment, $needle) && !str_contains($username, $needle) && !str_contains($currency, $needle)) {
                    continue;
                }
            }

            $filtered[] = $income;
        }

        $sort = $this->sanitizeIncomeSort(isset($query['sort']) ? (string) $query['sort'] : null);
        $dir = $this->sanitizeDirection(isset($query['dir']) ? (string) $query['dir'] : null);
        usort($filtered, function (Income $left, Income $right) use ($sort, $dir): int {
            $comparison = $this->compareIncome($left, $right, $sort);
            if ('desc' === $dir) {
                $comparison *= -1;
            }

            if (0 !== $comparison) {
                return $comparison;
            }

            return ((int) $left->getId()) <=> ((int) $right->getId());
        });

        $totalsByCurrency = [];
        $totalAmountInGel = 0.0;
        $totalOfficialRatedAmountInGel = 0.0;
        foreach ($filtered as $income) {
            $currencyCode = (string) $income->getCurrency()?->getCode();
            if ('' !== $currencyCode) {
                if (!isset($totalsByCurrency[$currencyCode])) {
                    $totalsByCurrency[$currencyCode] = 0.0;
                }
                $totalsByCurrency[$currencyCode] += (float) $income->getAmount();
            }

            if (null !== $income->getAmountInGel()) {
                $totalAmountInGel += (float) $income->getAmountInGel();
            }

            if (null !== $income->getOfficialRatedAmountInGel()) {
                $totalOfficialRatedAmountInGel += (float) $income->getOfficialRatedAmountInGel();
            }
        }

        $totalRecords = count($filtered);
        $perPage = $this->sanitizePerPage($query['perPage'] ?? null);
        $totalPages = max(1, (int) ceil($totalRecords / $perPage));
        $page = $this->sanitizePage($query['page'] ?? null, $totalPages);

        $offset = ($page - 1) * $perPage;
        $pageItems = array_slice($filtered, $offset, $perPage);

        $items = [];
        foreach ($pageItems as $income) {
            $items[] = $this->mapIncome($income);
        }

        $monthTabs = $this->buildIncomeMonthTabs($monthStart, $monthKey, $now);

        return new DashboardIncomeListPageDto(
            $monthStart->format('F Y'),
            $monthKey,
            $monthStart->modify('first day of previous month')->format('Y-m'),
            $monthStart->modify('first day of next month')->format('Y-m'),
            $monthTabs,
            $items,
            $totalRecords,
            $this->formatCurrencyTotals($totalsByCurrency),
            number_format($totalAmountInGel, 2, '.', ''),
            number_format($totalOfficialRatedAmountInGel, 4, '.', ''),
            $sort,
            $dir,
            $page,
            $perPage,
            $totalPages,
            $filterCurrency,
            $filterUser,
            $filterQuery,
            $availableCurrencies,
            $availableUsers,
            self::PER_PAGE_OPTIONS,
        );
    }

    public function buildSpendListViewData(array $query, \DateTimeImmutable $now): DashboardSpendListPageDto
    {
        $monthKey = $this->sanitizeMonthKey(isset($query['month']) ? (string) $query['month'] : null)
            ?? $now->format('Y-m');
        $monthStart = $this->monthStart($monthKey);

        $spends = $this->spendRepository->findForMonth($monthStart);
        $monthEnd = $monthStart->modify('last day of this month')->setTime(0, 0);
        $monthPlans = $this->spendingPlanRepository->findForMonth($monthStart, $monthEnd);

        $availableCurrencies = $this->extractAvailableCurrencies($spends);
        if ([] === $availableCurrencies) {
            foreach ($this->currencyRepository->findBy([], ['code' => 'ASC']) as $currency) {
                $availableCurrencies[] = $currency->getCode();
            }
        }

        $availableUsers = $this->extractAvailableUsers($spends);
        $availablePlans = $this->extractAvailablePlans($monthPlans);

        $filterCurrency = $this->sanitizeOptionalText($query['currency'] ?? null);
        $filterUser = $this->sanitizeOptionalText($query['user'] ?? null);
        $filterPlanId = $this->sanitizeOptionalText($query['plan'] ?? null);
        $filterQuery = $this->sanitizeOptionalText($query['q'] ?? null);

        $filtered = [];
        foreach ($spends as $spend) {
            if ('' !== $filterCurrency && $filterCurrency !== (string) $spend->getCurrency()?->getCode()) {
                continue;
            }

            if ('' !== $filterUser && $filterUser !== (string) $spend->getUserAdded()?->getUsername()) {
                continue;
            }

            if ('' !== $filterPlanId && $filterPlanId !== (string) $spend->getSpendingPlan()?->getId()) {
                continue;
            }

            if ('' !== $filterQuery) {
                $needle = mb_strtolower($filterQuery);
                $comment = mb_strtolower((string) $spend->getComment());
                $username = mb_strtolower((string) $spend->getUserAdded()?->getUsername());
                $planName = mb_strtolower((string) $spend->getSpendingPlan()?->getName());

                if (!str_contains($comment, $needle) && !str_contains($username, $needle) && !str_contains($planName, $needle)) {
                    continue;
                }
            }

            $filtered[] = $spend;
        }

        $sort = $this->sanitizeSort(isset($query['sort']) ? (string) $query['sort'] : null);
        $dir = $this->sanitizeDirection(isset($query['dir']) ? (string) $query['dir'] : null);
        usort($filtered, function (Spend $left, Spend $right) use ($sort, $dir): int {
            $comparison = $this->compareSpend($left, $right, $sort);
            if ('desc' === $dir) {
                $comparison *= -1;
            }

            if (0 !== $comparison) {
                return $comparison;
            }

            return ((int) $left->getId()) <=> ((int) $right->getId());
        });

        $totalsByCurrency = [];
        foreach ($filtered as $spend) {
            $currencyCode = (string) $spend->getCurrency()?->getCode();
            if ('' === $currencyCode) {
                continue;
            }

            if (!isset($totalsByCurrency[$currencyCode])) {
                $totalsByCurrency[$currencyCode] = 0.0;
            }
            $totalsByCurrency[$currencyCode] += (float) $spend->getAmount();
        }

        $totalRecords = count($filtered);
        $perPage = $this->sanitizePerPage($query['perPage'] ?? null);
        $totalPages = max(1, (int) ceil($totalRecords / $perPage));
        $page = $this->sanitizePage($query['page'] ?? null, $totalPages);

        $offset = ($page - 1) * $perPage;
        $pageItems = array_slice($filtered, $offset, $perPage);

        $items = [];
        foreach ($pageItems as $spend) {
            $items[] = $this->mapSpend($spend);
        }

        $monthTabs = $this->buildSpendMonthTabs($monthStart, $monthKey, $now);

        return new DashboardSpendListPageDto(
            $monthStart->format('F Y'),
            $monthKey,
            $monthStart->modify('first day of previous month')->format('Y-m'),
            $monthStart->modify('first day of next month')->format('Y-m'),
            $monthTabs,
            $items,
            $totalRecords,
            $this->formatCurrencyTotals($totalsByCurrency),
            $sort,
            $dir,
            $page,
            $perPage,
            $totalPages,
            $filterCurrency,
            $filterUser,
            $filterPlanId,
            $filterQuery,
            $availableCurrencies,
            $availableUsers,
            $availablePlans,
            self::PER_PAGE_OPTIONS,
        );
    }

    /**
     * @return array{monthKey: string, latestId: int, total: int}
     */
    public function buildSpendListVersionData(?string $monthKey, \DateTimeImmutable $now): array
    {
        $resolvedMonthKey = $this->sanitizeMonthKey($monthKey) ?? $now->format('Y-m');
        $monthStart = $this->monthStart($resolvedMonthKey);
        $version = $this->spendRepository->findMonthVersion($monthStart);

        return [
            'monthKey' => $resolvedMonthKey,
            'latestId' => $version['latestId'],
            'total' => $version['total'],
        ];
    }

    public function createIncomeDraft(): DashboardIncomeDraftDto
    {
        $draft = new DashboardIncomeDraftDto();
        $draft->setCurrency($this->currencyRepository->findOneByCode('GEL'));

        return $draft;
    }

    public function createIncomeDraftFromIncome(Income $income): DashboardIncomeDraftDto
    {
        $draft = new DashboardIncomeDraftDto();
        $draft
            ->setAmount($income->getAmount())
            ->setCurrency($income->getCurrency())
            ->setComment($income->getComment())
            ->setConvertToGel(null !== $income->getAmountInGel());

        return $draft;
    }

    public function createSpendDraft(\DateTimeImmutable $now): DashboardSpendDraftDto
    {
        $draft = new DashboardSpendDraftDto();
        $draft->setCurrency($this->currencyRepository->findOneByCode('GEL'));

        $spendDate = $now->setTime(0, 0);
        $draft->setSpendDate($spendDate);
        $draft->setSpendingPlan($this->spendingPlanRepository->findBestForDate($spendDate));

        return $draft;
    }

    public function createSpendDraftFromSpend(Spend $spend): DashboardSpendDraftDto
    {
        $draft = new DashboardSpendDraftDto();
        $draft
            ->setAmount($spend->getAmount())
            ->setCurrency($spend->getCurrency())
            ->setSpendingPlan($spend->getSpendingPlan())
            ->setSpendDate($spend->getSpendDate())
            ->setComment($spend->getComment());

        return $draft;
    }

    /**
     * @return list<SpendingPlan>
     */
    public function getSpendPlanChoicesForDate(\DateTimeImmutable $date): array
    {
        return $this->spendingPlanRepository->findForSpendSelection($date);
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
        $this->dispatchMonthlyBalanceRefresh(
            $income->getCreatedAt()->format('Y-m'),
            'income.create'
        );

        return new IncomeCreateResultDto(true);
    }

    public function updateIncome(Income $income, DashboardIncomeDraftDto $draft): IncomeCreateResultDto
    {
        $amount = $draft->getAmount();
        if (!is_numeric($amount) || (float) $amount < 0) {
            return new IncomeCreateResultDto(false, 'Amount must be a positive number.');
        }

        $currency = $draft->getCurrency();
        if (null === $currency) {
            return new IncomeCreateResultDto(false, 'Currency is required.');
        }

        $income
            ->setAmount(number_format((float) $amount, 2, '.', ''))
            ->setCurrency($currency)
            ->setComment($draft->getComment())
            ->setOfficialRatedAmountInGel(null);

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
        } else {
            $income->setAmountInGel(null);
            $income->setRate(null);
        }

        $monthKey = $income->getCreatedAt()->format('Y-m');
        $this->incomeRepository->save($income, true);
        $this->dispatchMonthlyBalanceRefresh($monthKey, 'income.update');

        return new IncomeCreateResultDto(true);
    }

    public function createSpend(User $user, DashboardSpendDraftDto $draft): SpendCreateResultDto
    {
        $amount = $draft->getAmount();
        if (!is_numeric($amount) || (float) $amount < 0) {
            return new SpendCreateResultDto(false, 'Amount must be a positive number.');
        }

        $currency = $draft->getCurrency();
        if (null === $currency) {
            return new SpendCreateResultDto(false, 'Currency is required.');
        }

        $spendingPlan = $draft->getSpendingPlan();
        if (null === $spendingPlan) {
            return new SpendCreateResultDto(false, 'Spending plan is required.');
        }

        $spendDate = $draft->getSpendDate()->setTime(0, 0);
        if ($spendDate < $spendingPlan->getDateFrom() || $spendDate > $spendingPlan->getDateTo()) {
            return new SpendCreateResultDto(false, 'Spend date must be inside selected spending plan period.');
        }

        $spend = (new Spend())
            ->setUserAdded($user)
            ->setAmount(number_format((float) $amount, 2, '.', ''))
            ->setCurrency($currency)
            ->setSpendingPlan($spendingPlan)
            ->setSpendDate($spendDate)
            ->setComment($draft->getComment());

        $this->spendRepository->save($spend, true);
        $this->dispatchMonthlyBalanceRefresh($spendDate->format('Y-m'), 'spend.create');

        return new SpendCreateResultDto(true);
    }

    public function updateSpend(Spend $spend, DashboardSpendDraftDto $draft): SpendCreateResultDto
    {
        $amount = $draft->getAmount();
        if (!is_numeric($amount) || (float) $amount < 0) {
            return new SpendCreateResultDto(false, 'Amount must be a positive number.');
        }

        $currency = $draft->getCurrency();
        if (null === $currency) {
            return new SpendCreateResultDto(false, 'Currency is required.');
        }

        $spendingPlan = $draft->getSpendingPlan();
        if (null === $spendingPlan) {
            return new SpendCreateResultDto(false, 'Spending plan is required.');
        }

        $previousMonthKey = $spend->getSpendDate()->format('Y-m');
        $spendDate = $draft->getSpendDate()->setTime(0, 0);
        if ($spendDate < $spendingPlan->getDateFrom() || $spendDate > $spendingPlan->getDateTo()) {
            return new SpendCreateResultDto(false, 'Spend date must be inside selected spending plan period.');
        }

        $spend
            ->setAmount(number_format((float) $amount, 2, '.', ''))
            ->setCurrency($currency)
            ->setSpendingPlan($spendingPlan)
            ->setSpendDate($spendDate)
            ->setComment($draft->getComment());

        $this->spendRepository->save($spend, true);
        $this->dispatchMonthlyBalanceRefresh($previousMonthKey, 'spend.update');
        $newMonthKey = $spendDate->format('Y-m');
        if ($newMonthKey !== $previousMonthKey) {
            $this->dispatchMonthlyBalanceRefresh($newMonthKey, 'spend.update');
        }

        return new SpendCreateResultDto(true);
    }

    public function deleteSpend(Spend $spend): void
    {
        $monthKey = $spend->getSpendDate()->format('Y-m');
        $this->spendRepository->remove($spend, true);
        $this->dispatchMonthlyBalanceRefresh($monthKey, 'spend.delete');
    }

    public function deleteIncome(Income $income): void
    {
        $monthKey = $income->getCreatedAt()->format('Y-m');
        $this->incomeRepository->remove($income, true);
        $this->dispatchMonthlyBalanceRefresh($monthKey, 'income.delete');
    }

    private function hasRole(User $user, string $role): bool
    {
        $roles = $user->getRoles();
        if (in_array($role, $roles, true)) {
            return true;
        }

        return 'ROLE_INCOMER' === $role && in_array('ROLE_ADMIN', $roles, true);
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

    private function mapSpend(Spend $spend): DashboardSpendItemDto
    {
        return new DashboardSpendItemDto(
            (int) $spend->getId(),
            (string) $spend->getUserAdded()?->getUsername(),
            $spend->getAmount(),
            (string) $spend->getCurrency()?->getCode(),
            (string) $spend->getSpendingPlan()?->getName(),
            $spend->getSpendDate()->format('Y-m-d'),
            $spend->getComment(),
            $spend->getCreatedAt()->format('Y-m-d H:i')
        );
    }

    private function dispatchMonthlyBalanceRefresh(string $monthKey, string $source): void
    {
        $this->eventDispatcher->dispatch(
            new MonthlyBalanceRefreshRequestedEvent(
                $monthKey,
                $source,
                new \DateTimeImmutable()
            )
        );
    }

    /**
     * @param array<string, float> $totalsByCurrency
     */
    private function formatCurrencyTotals(array $totalsByCurrency): string
    {
        if ([] === $totalsByCurrency) {
            return '0.00';
        }

        ksort($totalsByCurrency);
        $parts = [];
        foreach ($totalsByCurrency as $currency => $amount) {
            $parts[] = number_format($amount, 2, '.', '').' '.$currency;
        }

        return implode(' + ', $parts);
    }

    /**
     * @param list<Spend> $spends
     *
     * @return list<string>
     */
    private function extractAvailableCurrencies(array $spends): array
    {
        $keys = [];
        foreach ($spends as $spend) {
            $code = (string) $spend->getCurrency()?->getCode();
            if ('' === $code) {
                continue;
            }
            $keys[$code] = true;
        }

        $currencies = array_keys($keys);
        sort($currencies);

        return $currencies;
    }

    /**
     * @param list<Spend> $spends
     *
     * @return list<string>
     */
    private function extractAvailableUsers(array $spends): array
    {
        $keys = [];
        foreach ($spends as $spend) {
            $username = (string) $spend->getUserAdded()?->getUsername();
            if ('' === $username) {
                continue;
            }
            $keys[$username] = true;
        }

        $users = array_keys($keys);
        sort($users);

        return $users;
    }

    /**
     * @param list<Income> $incomes
     *
     * @return list<string>
     */
    private function extractAvailableIncomeCurrencies(array $incomes): array
    {
        $keys = [];
        foreach ($incomes as $income) {
            $code = (string) $income->getCurrency()?->getCode();
            if ('' === $code) {
                continue;
            }
            $keys[$code] = true;
        }

        $currencies = array_keys($keys);
        sort($currencies);

        return $currencies;
    }

    /**
     * @param list<Income> $incomes
     *
     * @return list<string>
     */
    private function extractAvailableIncomeUsers(array $incomes): array
    {
        $keys = [];
        foreach ($incomes as $income) {
            $username = (string) $income->getUserAdded()?->getUsername();
            if ('' === $username) {
                continue;
            }
            $keys[$username] = true;
        }

        $users = array_keys($keys);
        sort($users);

        return $users;
    }

    /**
     * @param list<SpendingPlan> $plans
     *
     * @return list<array{id: int, label: string}>
     */
    private function extractAvailablePlans(array $plans): array
    {
        $items = [];
        foreach ($plans as $plan) {
            $id = (int) $plan->getId();
            if ($id <= 0) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'label' => sprintf(
                    '%s (%s - %s)',
                    $plan->getName(),
                    $plan->getDateFrom()->format('Y-m-d'),
                    $plan->getDateTo()->format('Y-m-d')
                ),
            ];
        }

        usort($items, static fn (array $left, array $right): int => strcmp($left['label'], $right['label']));

        return $items;
    }

    /**
     * @return list<DashboardMonthTabDto>
     */
    private function buildSpendMonthTabs(
        \DateTimeImmutable $selectedMonthStart,
        string $selectedMonthKey,
        \DateTimeImmutable $now,
    ): array {
        $keys = [];
        foreach ($this->spendRepository->findMonthKeys() as $monthKey) {
            $keys[$monthKey] = true;
        }

        $keys[$now->format('Y-m')] = true;
        $keys[$selectedMonthKey] = true;
        $keys[$selectedMonthStart->modify('first day of previous month')->format('Y-m')] = true;
        $keys[$selectedMonthStart->modify('first day of next month')->format('Y-m')] = true;

        $monthKeys = array_keys($keys);
        sort($monthKeys);

        $tabs = [];
        foreach ($monthKeys as $monthKey) {
            $tabs[] = new DashboardMonthTabDto(
                $monthKey,
                $this->monthStart($monthKey)->format('F Y'),
                $monthKey === $selectedMonthKey,
            );
        }

        return $tabs;
    }

    /**
     * @return list<DashboardMonthTabDto>
     */
    private function buildIncomeMonthTabs(
        \DateTimeImmutable $selectedMonthStart,
        string $selectedMonthKey,
        \DateTimeImmutable $now,
    ): array {
        $keys = [];
        foreach ($this->incomeRepository->findMonthKeys() as $monthKey) {
            $keys[$monthKey] = true;
        }

        $keys[$now->format('Y-m')] = true;
        $keys[$selectedMonthKey] = true;
        $keys[$selectedMonthStart->modify('first day of previous month')->format('Y-m')] = true;
        $keys[$selectedMonthStart->modify('first day of next month')->format('Y-m')] = true;

        $monthKeys = array_keys($keys);
        sort($monthKeys);

        $tabs = [];
        foreach ($monthKeys as $monthKey) {
            $tabs[] = new DashboardMonthTabDto(
                $monthKey,
                $this->monthStart($monthKey)->format('F Y'),
                $monthKey === $selectedMonthKey,
            );
        }

        return $tabs;
    }

    private function compareIncome(Income $left, Income $right, string $sort): int
    {
        return match ($sort) {
            self::SORT_AMOUNT => (float) $left->getAmount() <=> (float) $right->getAmount(),
            self::SORT_CURRENCY => strcmp(
                (string) $left->getCurrency()?->getCode(),
                (string) $right->getCurrency()?->getCode()
            ),
            self::SORT_USERNAME => strcmp(
                (string) $left->getUserAdded()?->getUsername(),
                (string) $right->getUserAdded()?->getUsername()
            ),
            self::SORT_AMOUNT_IN_GEL => $this->compareNullableDecimalStrings(
                $left->getAmountInGel(),
                $right->getAmountInGel()
            ),
            self::SORT_OFFICIAL_RATED_AMOUNT_IN_GEL => $this->compareNullableDecimalStrings(
                $left->getOfficialRatedAmountInGel(),
                $right->getOfficialRatedAmountInGel()
            ),
            self::SORT_RATE => $this->compareNullableDecimalStrings(
                $left->getRate(),
                $right->getRate()
            ),
            default => $left->getCreatedAt() <=> $right->getCreatedAt(),
        };
    }

    private function compareSpend(Spend $left, Spend $right, string $sort): int
    {
        return match ($sort) {
            self::SORT_AMOUNT => (float) $left->getAmount() <=> (float) $right->getAmount(),
            self::SORT_CURRENCY => strcmp(
                (string) $left->getCurrency()?->getCode(),
                (string) $right->getCurrency()?->getCode()
            ),
            self::SORT_USERNAME => strcmp(
                (string) $left->getUserAdded()?->getUsername(),
                (string) $right->getUserAdded()?->getUsername()
            ),
            self::SORT_SPENDING_PLAN => strcmp(
                (string) $left->getSpendingPlan()?->getName(),
                (string) $right->getSpendingPlan()?->getName()
            ),
            self::SORT_CREATED_AT => $left->getCreatedAt() <=> $right->getCreatedAt(),
            default => $left->getSpendDate() <=> $right->getSpendDate(),
        };
    }

    private function sanitizeSort(?string $sort): string
    {
        $allowed = [
            self::SORT_SPEND_DATE,
            self::SORT_CREATED_AT,
            self::SORT_AMOUNT,
            self::SORT_CURRENCY,
            self::SORT_USERNAME,
            self::SORT_SPENDING_PLAN,
        ];

        if (null === $sort || !in_array($sort, $allowed, true)) {
            return self::SORT_SPEND_DATE;
        }

        return $sort;
    }

    private function sanitizeIncomeSort(?string $sort): string
    {
        $allowed = [
            self::SORT_CREATED_AT,
            self::SORT_AMOUNT,
            self::SORT_CURRENCY,
            self::SORT_USERNAME,
            self::SORT_AMOUNT_IN_GEL,
            self::SORT_OFFICIAL_RATED_AMOUNT_IN_GEL,
            self::SORT_RATE,
        ];

        if (null === $sort || !in_array($sort, $allowed, true)) {
            return self::SORT_CREATED_AT;
        }

        return $sort;
    }

    private function sanitizeDirection(?string $direction): string
    {
        if ('asc' === $direction) {
            return 'asc';
        }

        return 'desc';
    }

    private function sanitizePerPage(mixed $value): int
    {
        $resolved = is_scalar($value) ? (int) $value : 0;
        if ($resolved < 1 || $resolved > 100) {
            return self::PER_PAGE_OPTIONS[0];
        }

        return $resolved;
    }

    private function sanitizePage(mixed $value, int $totalPages): int
    {
        $page = is_scalar($value) ? (int) $value : 1;
        if ($page < 1) {
            $page = 1;
        }

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        return $page;
    }

    private function sanitizeOptionalText(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
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

    private function compareNullableDecimalStrings(?string $left, ?string $right): int
    {
        if (null === $left && null === $right) {
            return 0;
        }

        if (null === $left) {
            return 1;
        }

        if (null === $right) {
            return -1;
        }

        return (float) $left <=> (float) $right;
    }
}
