<?php

declare(strict_types=1);

namespace App\Controller\Api\Dashboard;

use App\DTO\Controller\Web\DashboardIncomeWidgetDto;
use App\DTO\Controller\Web\DashboardSpendItemDto;
use App\DTO\Controller\Web\DashboardSpendWidgetDto;
use App\DTO\Controller\Web\DashboardIncomeDraftDto;
use App\DTO\Controller\Web\DashboardSpendDraftDto;
use App\Entity\Currency;
use App\Entity\SpendingPlan;
use App\Entity\User;
use App\Repository\CurrencyRepository;
use App\Repository\SpendingPlanRepository;
use App\Service\Controller\Web\DashboardControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/dashboard', name: 'api_dashboard_')]
#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardControllerService $service,
        private readonly CurrencyRepository $currencyRepository,
        private readonly SpendingPlanRepository $spendingPlanRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'overview', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        $user = $this->requireUser();

        return $this->json([
            'success' => true,
            'payload' => $this->buildDashboardPayload($user),
        ]);
    }

    #[Route('/spends', name: 'create_spend', methods: ['POST'])]
    public function createSpend(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $payload = $this->decodeJsonPayload($request);

        $draft = $this->hydrateSpendDraft($payload, new \DateTimeImmutable());
        $result = $this->service->createSpend($user, $draft);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'error' => $result->errorMessage ?? 'Unable to create spend.',
            ], 422);
        }

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('flash.spend_added'),
            'payload' => $this->buildDashboardPayload($user),
        ]);
    }

    #[Route('/incomes', name: 'create_income', methods: ['POST'])]
    public function createIncome(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if (!$this->isGranted('ROLE_INCOMER')) {
            return $this->json([
                'success' => false,
                'error' => 'Income creation is allowed for incomer role only.',
            ], 403);
        }

        $payload = $this->decodeJsonPayload($request);
        $draft = $this->hydrateIncomeDraft($payload);
        $result = $this->service->createIncome($user, $draft);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'error' => $result->errorMessage ?? 'Unable to create income.',
            ], 422);
        }

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('flash.income_added'),
            'payload' => $this->buildDashboardPayload($user),
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User is not authenticated.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonPayload(Request $request): array
    {
        $content = trim($request->getContent());
        if ('' === $content) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateSpendDraft(array $payload, \DateTimeImmutable $now): DashboardSpendDraftDto
    {
        $draft = $this->service->createSpendDraft($now);

        if (array_key_exists('amount', $payload)) {
            $draft->setAmount((string) $payload['amount']);
        }

        if (array_key_exists('currencyId', $payload)) {
            $currency = $this->resolveCurrency($payload['currencyId']);
            $draft->setCurrency($currency);
        }

        if (array_key_exists('spendingPlanId', $payload)) {
            $spendingPlan = $this->resolveSpendingPlan($payload['spendingPlanId']);
            $draft->setSpendingPlan($spendingPlan);
        }

        if (array_key_exists('spendDate', $payload) && is_string($payload['spendDate'])) {
            $dateValue = trim($payload['spendDate']);
            if ('' !== $dateValue) {
                try {
                    $draft->setSpendDate(new \DateTimeImmutable($dateValue));
                } catch (\Exception) {
                    // Invalid date is handled by service validation.
                }
            }
        }

        if (array_key_exists('comment', $payload)) {
            $comment = $payload['comment'];
            $draft->setComment(is_string($comment) ? $comment : null);
        }

        return $draft;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateIncomeDraft(array $payload): DashboardIncomeDraftDto
    {
        $draft = $this->service->createIncomeDraft();

        if (array_key_exists('amount', $payload)) {
            $draft->setAmount((string) $payload['amount']);
        }

        if (array_key_exists('currencyId', $payload)) {
            $currency = $this->resolveCurrency($payload['currencyId']);
            $draft->setCurrency($currency);
        }

        if (array_key_exists('comment', $payload)) {
            $comment = $payload['comment'];
            $draft->setComment(is_string($comment) ? $comment : null);
        }

        if (array_key_exists('convertToGel', $payload)) {
            $draft->setConvertToGel((bool) $payload['convertToGel']);
        }

        return $draft;
    }

    private function resolveCurrency(mixed $rawCurrencyId): ?Currency
    {
        $currencyId = (int) $rawCurrencyId;
        if ($currencyId <= 0) {
            return null;
        }

        $currency = $this->currencyRepository->find($currencyId);

        return $currency instanceof Currency ? $currency : null;
    }

    private function resolveSpendingPlan(mixed $rawSpendingPlanId): ?SpendingPlan
    {
        $spendingPlanId = (int) $rawSpendingPlanId;
        if ($spendingPlanId <= 0) {
            return null;
        }

        $spendingPlan = $this->spendingPlanRepository->find($spendingPlanId);

        return $spendingPlan instanceof SpendingPlan ? $spendingPlan : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardPayload(User $user): array
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

        $canManageIncome = $this->isGranted('ROLE_INCOMER');
        $incomeFormPayload = null;
        if ($canManageIncome) {
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
