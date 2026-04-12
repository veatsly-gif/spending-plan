<?php

declare(strict_types=1);

namespace App\Service\Api;

use App\DTO\Controller\Web\DashboardIncomeWidgetDto;
use App\DTO\Controller\Web\DashboardSpendItemDto;
use App\DTO\Controller\Web\DashboardSpendWidgetDto;
use App\Entity\Currency;
use App\Entity\SpendingPlan;
use App\Entity\User;
use App\Repository\CurrencyRepository;
use App\Service\Controller\Web\DashboardControllerService;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class DashboardOverviewPayloadFactory
{
    public function __construct(
        private DashboardControllerService $service,
        private CurrencyRepository $currencyRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $now = new \DateTimeImmutable();
        $viewData = $this->service->buildViewData($user, $now);
        $spendDraft = $this->service->createSpendDraft($now);

        $currencies = $this->currencyRepository->findBy([], ['code' => 'ASC']);
        $currencyOptions = array_map(
            static fn (Currency $currency): array => [
                'id' => (int) $currency->getId(),
                'code' => $currency->getCode(),
            ],
            $currencies
        );

        $spendingPlanChoices = $this->service->getSpendPlanChoicesForDate($spendDraft->getSpendDate());
        $spendingPlanOptions = array_map(
            static fn (SpendingPlan $spendingPlan): array => [
                'id' => (int) $spendingPlan->getId(),
                'name' => $spendingPlan->getName(),
                'dateFrom' => $spendingPlan->getDateFrom()->format('Y-m-d'),
                'dateTo' => $spendingPlan->getDateTo()->format('Y-m-d'),
            ],
            $spendingPlanChoices
        );

        $incomeFormPayload = null;
        if ($this->authorizationChecker->isGranted('ROLE_INCOMER')) {
            $incomeDraft = $this->service->createIncomeDraft();
            $incomeFormPayload = [
                'defaults' => [
                    'amount' => $incomeDraft->getAmount(),
                    'currencyId' => (int) ($incomeDraft->getCurrency()?->getId() ?? 0),
                    'comment' => $incomeDraft->getComment() ?? '',
                    'convertToGel' => $incomeDraft->isConvertToGel(),
                ],
                'currencies' => $currencyOptions,
            ];
        }

        return [
            'user' => [
                'identifier' => $user->getUserIdentifier(),
                'roles' => $user->getRoles(),
            ],
            'isIncomer' => $viewData->isIncomer,
            'incomeWidget' => $this->normalizeIncomeWidget($viewData->incomeWidget),
            'spendWidget' => $this->normalizeSpendWidget($viewData->spendWidget),
            'forms' => [
                'spend' => [
                    'defaults' => [
                        'amount' => $spendDraft->getAmount(),
                        'currencyId' => (int) ($spendDraft->getCurrency()?->getId() ?? 0),
                        'spendingPlanId' => (int) ($spendDraft->getSpendingPlan()?->getId() ?? 0),
                        'spendDate' => $spendDraft->getSpendDate()->format('Y-m-d'),
                        'comment' => $spendDraft->getComment() ?? '',
                    ],
                    'currencies' => $currencyOptions,
                    'spendingPlans' => $spendingPlanOptions,
                ],
                'income' => $incomeFormPayload,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeIncomeWidget(DashboardIncomeWidgetDto $widget): array
    {
        return [
            'monthLabel' => $widget->monthLabel,
            'totalIncomeGel' => $widget->totalIncomeGel,
            'regularAndPlannedGel' => $widget->regularAndPlannedGel,
            'availableToSpendGel' => $widget->availableToSpendGel,
            'eurGelRate' => $widget->eurGelRate,
            'usdtGelRate' => $widget->usdtGelRate,
            'ratesUpdatedAtLabel' => $widget->ratesUpdatedAtLabel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeSpendWidget(DashboardSpendWidgetDto $widget): array
    {
        return [
            'monthLabel' => $widget->monthLabel,
            'currentTimePlanName' => $widget->currentTimePlanName,
            'currentTimePlanSpentGel' => $widget->currentTimePlanSpentGel,
            'currentTimePlanLimitGel' => $widget->currentTimePlanLimitGel,
            'currentTimePlanProgressPercent' => $widget->currentTimePlanProgressPercent,
            'currentTimePlanProgressBarPercent' => $widget->currentTimePlanProgressBarPercent,
            'currentTimePlanProgressTone' => $widget->currentTimePlanProgressTone,
            'monthSpentGel' => $widget->monthSpentGel,
            'monthLimitGel' => $widget->monthLimitGel,
            'monthSpendProgressPercent' => $widget->monthSpendProgressPercent,
            'monthSpendProgressBarPercent' => $widget->monthSpendProgressBarPercent,
            'monthSpendProgressTone' => $widget->monthSpendProgressTone,
            'todaySpentGel' => $widget->todaySpentGel,
            'recentSpends' => array_map(
                static fn (DashboardSpendItemDto $spend): array => [
                    'id' => $spend->id,
                    'username' => $spend->username,
                    'amount' => $spend->amount,
                    'currencyCode' => $spend->currencyCode,
                    'spendingPlanName' => $spend->spendingPlanName,
                    'spendDateLabel' => $spend->spendDateLabel,
                    'comment' => $spend->comment,
                    'createdAtLabel' => $spend->createdAtLabel,
                ],
                $widget->recentSpends
            ),
        ];
    }
}
