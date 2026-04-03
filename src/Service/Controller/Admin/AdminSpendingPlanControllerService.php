<?php

declare(strict_types=1);

namespace App\Service\Controller\Admin;

use App\DTO\Controller\Admin\AdminActionResultDto;
use App\DTO\Controller\Admin\AdminSpendingPlanApproveResultDto;
use App\DTO\Controller\Admin\AdminSpendingPlanDraftDto;
use App\DTO\Controller\Admin\AdminSpendingPlanMonthTabDto;
use App\DTO\Controller\Admin\AdminSpendingPlanPopupDto;
use App\DTO\Controller\Admin\AdminSpendingPlansIndexViewDto;
use App\Event\MonthlyBalanceRefreshRequestedEvent;
use App\Entity\SpendingPlan;
use App\Repository\CurrencyRepository;
use App\Repository\SpendingPlanRepository;
use App\Service\SpendingPlanSuggestionCacheService;
use App\Util\RussianCalendarFormatter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class AdminSpendingPlanControllerService
{
    public function __construct(
        private readonly SpendingPlanSuggestionCacheService $suggestionCacheService,
        private readonly CurrencyRepository $currencyRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function buildIndexViewData(
        ?string $selectedMonthKey,
        SpendingPlanRepository $spendingPlanRepository,
    ): AdminSpendingPlansIndexViewDto {
        $now = new \DateTimeImmutable();
        $currentMonthStart = $this->monthStart($now->format('Y-m'));
        $nextMonthStart = $currentMonthStart->modify('first day of next month');

        $needsAttention = (int) $now->format('j') >= 25
            && 0 === $spendingPlanRepository->countForMonth(
                $nextMonthStart,
                $nextMonthStart->modify('last day of this month')
            );

        $defaultMonth = $needsAttention
            ? $nextMonthStart->format('Y-m')
            : $currentMonthStart->format('Y-m');

        $activeMonthKey = $this->sanitizeMonthKey($selectedMonthKey) ?? $defaultMonth;
        $activeMonthStart = $this->monthStart($activeMonthKey);
        $activeMonthEnd = $activeMonthStart->modify('last day of this month');

        $monthTabs = $this->buildMonthTabs(
            $spendingPlanRepository,
            $activeMonthKey,
            $currentMonthStart,
            $nextMonthStart,
            $needsAttention
        );

        $suggestedPlans = $this->suggestionCacheService->getSuggestions($activeMonthKey);
        $existingPlans = $spendingPlanRepository->findForMonth($activeMonthStart, $activeMonthEnd, $now);
        $currencies = $this->currencyRepository->findBy([], ['code' => 'ASC']);
        $currencyCodes = [];
        foreach ($currencies as $currency) {
            $currencyCodes[] = $currency->getCode();
        }

        return new AdminSpendingPlansIndexViewDto(
            $monthTabs,
            $activeMonthKey,
            RussianCalendarFormatter::monthYear($activeMonthStart),
            $currencyCodes,
            $suggestedPlans,
            $existingPlans,
            new AdminSpendingPlanPopupDto(false, '', '', '')
        );
    }

    public function createDraftSpendingPlan(): AdminSpendingPlanDraftDto
    {
        $today = (new \DateTimeImmutable())->setTime(0, 0);
        $gel = $this->currencyRepository->findOneByCode('GEL');
        if (null === $gel) {
            throw new \RuntimeException('Currency GEL is not configured.');
        }

        $draft = (new SpendingPlan())
            ->setName('Custom plan')
            ->setPlanType(SpendingPlan::PLAN_TYPE_CUSTOM)
            ->setDateFrom($today)
            ->setDateTo($today)
            ->setLimitAmount('0.00')
            ->setCurrency($gel)
            ->setWeight(1)
            ->setIsSystem(false);

        return new AdminSpendingPlanDraftDto($draft);
    }

    public function createSpendingPlan(
        SpendingPlan $spendingPlan,
        SpendingPlanRepository $spendingPlanRepository,
    ): AdminActionResultDto {
        $validation = $this->validateSpendingPlan($spendingPlan);
        if (!$validation->success) {
            return $validation;
        }

        $spendingPlan->touch();
        $spendingPlanRepository->save($spendingPlan, true);

        $this->removeSuggestionBySignature($spendingPlan);
        $this->dispatchRefreshForPlanMonths($spendingPlan, 'spending_plan.create');

        return new AdminActionResultDto(true);
    }

    public function updateSpendingPlan(
        SpendingPlan $spendingPlan,
        SpendingPlanRepository $spendingPlanRepository,
    ): AdminActionResultDto {
        $validation = $this->validateSpendingPlan($spendingPlan);
        if (!$validation->success) {
            return $validation;
        }

        $spendingPlan->touch();
        $spendingPlanRepository->save($spendingPlan, true);
        $this->dispatchRefreshForPlanMonths($spendingPlan, 'spending_plan.update');

        return new AdminActionResultDto(true);
    }

    public function removeSpendingPlan(
        SpendingPlan $spendingPlan,
        SpendingPlanRepository $spendingPlanRepository,
    ): AdminActionResultDto {
        $this->dispatchRefreshForPlanMonths($spendingPlan, 'spending_plan.delete');
        $spendingPlanRepository->remove($spendingPlan, true);

        return new AdminActionResultDto(true);
    }

    public function approveSuggestion(
        string $monthKey,
        string $suggestionId,
        string $limitAmount,
        ?string $currencyCode,
        ?string $weight,
        ?string $note,
        SpendingPlanRepository $spendingPlanRepository,
    ): AdminSpendingPlanApproveResultDto {
        $removal = $this->suggestionCacheService->removeSuggestion($monthKey, $suggestionId);
        if (!$removal['removed']) {
            return new AdminSpendingPlanApproveResultDto(false, null, 'Suggestion not found.');
        }

        $suggestion = $removal['suggestion'];
        if (null === $suggestion) {
            return new AdminSpendingPlanApproveResultDto(false, null, 'Suggestion not found.');
        }

        $amount = is_numeric($limitAmount) ? (float) $limitAmount : (float) $suggestion->limitAmount;
        if ($amount < 0) {
            return new AdminSpendingPlanApproveResultDto(false, null, 'Limit must be positive.');
        }
        $resolvedWeight = is_numeric((string) $weight)
            ? (int) $weight
            : $suggestion->weight;
        if ($resolvedWeight < 0) {
            return new AdminSpendingPlanApproveResultDto(false, null, 'Weight must be positive.');
        }
        $resolvedNote = null !== $note ? trim($note) : null;
        if ('' === (string) $resolvedNote) {
            $resolvedNote = $suggestion->note;
        }

        $plan = (new SpendingPlan())
            ->setName($suggestion->name)
            ->setPlanType($suggestion->planType)
            ->setDateFrom(new \DateTimeImmutable($suggestion->dateFrom))
            ->setDateTo(new \DateTimeImmutable($suggestion->dateTo))
            ->setLimitAmount(number_format($amount, 2, '.', ''))
            ->setWeight($resolvedWeight)
            ->setIsSystem(true)
            ->setNote($resolvedNote)
            ->touch();

        $selectedCurrencyCode = null !== $currencyCode && '' !== trim($currencyCode)
            ? $currencyCode
            : $suggestion->currency;
        $currency = $this->currencyRepository->findOneByCode($selectedCurrencyCode);
        if (null === $currency) {
            return new AdminSpendingPlanApproveResultDto(false, null, 'Currency not found.');
        }
        $plan->setCurrency($currency);

        $spendingPlanRepository->save($plan, true);
        $this->dispatchRefreshForPlanMonths($plan, 'spending_plan.approve');

        return new AdminSpendingPlanApproveResultDto(true, $plan);
    }

    public function deleteSuggestion(string $monthKey, string $suggestionId): AdminActionResultDto
    {
        $removal = $this->suggestionCacheService->removeSuggestion($monthKey, $suggestionId);
        if (!$removal['removed']) {
            return new AdminActionResultDto(false, 'Suggestion not found.');
        }

        return new AdminActionResultDto(true);
    }

    private function validateSpendingPlan(SpendingPlan $spendingPlan): AdminActionResultDto
    {
        if ($spendingPlan->getDateTo() < $spendingPlan->getDateFrom()) {
            return new AdminActionResultDto(
                false,
                'Date "to" must be greater than or equal to date "from".'
            );
        }

        if ($spendingPlan->getWeight() < 0) {
            return new AdminActionResultDto(false, 'Weight must be greater than or equal to zero.');
        }

        if ((float) $spendingPlan->getLimitAmount() < 0.0) {
            return new AdminActionResultDto(
                false,
                'Limit amount must be greater than or equal to zero.'
            );
        }

        if (null === $spendingPlan->getCurrency()) {
            return new AdminActionResultDto(false, 'Currency is required.');
        }

        return new AdminActionResultDto(true);
    }

    /**
     * @return list<AdminSpendingPlanMonthTabDto>
     */
    private function buildMonthTabs(
        SpendingPlanRepository $spendingPlanRepository,
        string $activeMonthKey,
        \DateTimeImmutable $currentMonthStart,
        \DateTimeImmutable $nextMonthStart,
        bool $nextMonthNeedsAttention,
    ): array {
        $keys = [
            $currentMonthStart->format('Y-m') => true,
            $nextMonthStart->format('Y-m') => true,
        ];

        /** @var list<SpendingPlan> $allPlans */
        $allPlans = $spendingPlanRepository->findBy([], ['dateFrom' => 'ASC']);
        foreach ($allPlans as $plan) {
            $keys[$plan->getDateFrom()->format('Y-m')] = true;
        }

        $monthKeys = array_keys($keys);
        sort($monthKeys);

        $tabs = [];
        foreach ($monthKeys as $monthKey) {
            $monthStart = $this->monthStart($monthKey);
            $tabs[] = new AdminSpendingPlanMonthTabDto(
                $monthKey,
                RussianCalendarFormatter::monthYear($monthStart),
                $monthKey === $activeMonthKey,
                $nextMonthNeedsAttention && $monthKey === $nextMonthStart->format('Y-m')
            );
        }

        return $tabs;
    }

    private function monthStart(string $monthKey): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $monthKey.'-01 00:00:00')
            ?: new \DateTimeImmutable('first day of this month');
    }

    private function sanitizeMonthKey(?string $monthKey): ?string
    {
        if (null === $monthKey) {
            return null;
        }

        if (1 !== preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            return null;
        }

        return $monthKey;
    }

    private function removeSuggestionBySignature(SpendingPlan $spendingPlan): void
    {
        $monthKey = $spendingPlan->getDateFrom()->format('Y-m');
        $suggestions = $this->suggestionCacheService->getSuggestions($monthKey);
        if ([] === $suggestions) {
            return;
        }

        foreach ($suggestions as $suggestion) {
            if ($suggestion->name !== $spendingPlan->getName()) {
                continue;
            }

            if ($suggestion->dateFrom !== $spendingPlan->getDateFrom()->format('Y-m-d')) {
                continue;
            }

            if ($suggestion->dateTo !== $spendingPlan->getDateTo()->format('Y-m-d')) {
                continue;
            }

            $this->suggestionCacheService->removeSuggestion($monthKey, $suggestion->id);

            return;
        }
    }

    private function dispatchRefreshForPlanMonths(SpendingPlan $spendingPlan, string $source): void
    {
        $from = $spendingPlan->getDateFrom()->setTime(0, 0)->modify('first day of this month');
        $to = $spendingPlan->getDateTo()->setTime(0, 0)->modify('first day of this month');

        $cursor = $from;
        while ($cursor <= $to) {
            $this->eventDispatcher->dispatch(
                new MonthlyBalanceRefreshRequestedEvent(
                    $cursor->format('Y-m'),
                    $source,
                    new \DateTimeImmutable()
                )
            );
            $cursor = $cursor->modify('+1 month');
        }
    }
}
