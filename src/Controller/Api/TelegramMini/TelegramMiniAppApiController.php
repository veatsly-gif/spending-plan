<?php

declare(strict_types=1);

namespace App\Controller\Api\TelegramMini;

use App\DTO\Controller\Web\DashboardSpendDraftDto;
use App\DTO\Controller\Web\DashboardSpendItemDto;
use App\DTO\Controller\Web\DashboardSpendListPageDto;
use App\Entity\Currency;
use App\Entity\Spend;
use App\Entity\SpendingPlan;
use App\Entity\User;
use App\Repository\CurrencyRepository;
use App\Repository\SpendRepository;
use App\Repository\SpendingPlanRepository;
use App\Service\Api\DashboardOverviewPayloadFactory;
use App\Service\Controller\Web\DashboardControllerService;
use App\Service\TelegramMiniApp\TelegramMiniAppUserResolver;
use App\Service\UserMetadataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/telegram/mini', name: 'api_telegram_mini_')]
final class TelegramMiniAppApiController extends AbstractController
{
    public function __construct(
        private readonly TelegramMiniAppUserResolver $telegramMiniUserResolver,
        private readonly UserMetadataService $userMetadataService,
        private readonly DashboardControllerService $dashboardControllerService,
        private readonly DashboardOverviewPayloadFactory $overviewPayloadFactory,
        private readonly CurrencyRepository $currencyRepository,
        private readonly SpendingPlanRepository $spendingPlanRepository,
        private readonly SpendRepository $spendRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/spend-form', name: 'spend_form', methods: ['GET'])]
    public function spendFormShape(Request $request): JsonResponse
    {
        $user = $this->requireTelegramMiniUser($request);
        if (!$user instanceof User) {
            return $this->miniDenied();
        }

        $this->applyUserLocale($request, $user);
        $rawDate = $request->query->get('spendDate');
        $dateStr = is_string($rawDate) ? trim($rawDate) : '';
        try {
            $d = '' !== $dateStr ? new \DateTimeImmutable($dateStr) : new \DateTimeImmutable('today');
        } catch (\Exception) {
            $d = new \DateTimeImmutable('today');
        }

        $draft = $this->dashboardControllerService->createSpendDraft($d);
        $plans = $this->dashboardControllerService->getSpendPlanChoicesForDate($draft->getSpendDate());

        return $this->json([
            'success' => true,
            'payload' => $this->buildSpendFormShape($draft, $plans),
        ]);
    }

    #[Route('/bootstrap', name: 'bootstrap', methods: ['GET'])]
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $this->requireTelegramMiniUser($request);
        if (!$user instanceof User) {
            return $this->miniDenied();
        }

        $this->applyUserLocale($request, $user);
        $preferences = $this->userMetadataService->getPreferences($user);
        $overview = $this->overviewPayloadFactory->build($user);

        return $this->json([
            'success' => true,
            'preferences' => $preferences,
            'overview' => $overview,
        ]);
    }

    #[Route('/preferences', name: 'preferences_get', methods: ['GET'])]
    public function getPreferences(Request $request): JsonResponse
    {
        $user = $this->requireTelegramMiniUser($request);
        if (!$user instanceof User) {
            return $this->miniDenied();
        }

        return $this->json([
            'success' => true,
            'preferences' => $this->userMetadataService->getPreferences($user),
        ]);
    }

    #[Route('/preferences', name: 'preferences_update', methods: ['POST'])]
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $this->requireTelegramMiniUser($request);
        if (!$user instanceof User) {
            return $this->miniDenied();
        }

        $payload = $this->decodeJsonPayload($request);
        $allowedKeys = ['language', 'theme'];
        $preferencesToUpdate = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $preferencesToUpdate[$key] = $payload[$key];
            }
        }

        if ([] === $preferencesToUpdate) {
            return $this->json([
                'success' => false,
                'error' => 'No valid preferences to update.',
            ], 400);
        }

        if (array_key_exists('language', $preferencesToUpdate) && !in_array($preferencesToUpdate['language'], ['en', 'ru'], true)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid language value. Must be "en" or "ru".',
            ], 400);
        }

        if (array_key_exists('theme', $preferencesToUpdate) && !in_array($preferencesToUpdate['theme'], ['light', 'dark'], true)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid theme value. Must be "light" or "dark".',
            ], 400);
        }

        $metadata = $this->userMetadataService->updatePreferences($user, $preferencesToUpdate);

        return $this->json([
            'success' => true,
            'preferences' => $metadata->getPreferences(),
        ]);
    }

    #[Route('/spends', name: 'spends_list', methods: ['GET'])]
    public function listSpends(Request $request): JsonResponse
    {
        $user = $this->requireTelegramMiniUser($request);
        if (!$user instanceof User) {
            return $this->miniDenied();
        }

        $this->applyUserLocale($request, $user);
        $dto = $this->dashboardControllerService->buildSpendListViewData($request->query->all(), new \DateTimeImmutable());

        return $this->json([
            'success' => true,
            'payload' => $this->serializeSpendListPage($dto),
        ]);
    }

    #[Route('/spends', name: 'spends_create', methods: ['POST'])]
    public function createSpend(Request $request): JsonResponse
    {
        $user = $this->requireTelegramMiniUser($request);
        if (!$user instanceof User) {
            return $this->miniDenied();
        }

        $this->applyUserLocale($request, $user);
        $payload = $this->decodeJsonPayload($request);
        $draft = $this->hydrateSpendDraft($payload, new \DateTimeImmutable());
        $result = $this->dashboardControllerService->createSpend($user, $draft);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'error' => $result->errorMessage ?? 'Unable to create spend.',
            ], 422);
        }

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('flash.spend_added'),
            'payload' => $this->overviewPayloadFactory->build($user),
        ]);
    }

    #[Route('/spends/{id}', name: 'spends_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function showSpend(int $id, Request $request): JsonResponse
    {
        $user = $this->requireTelegramMiniUser($request);
        if (!$user instanceof User) {
            return $this->miniDenied();
        }

        $this->applyUserLocale($request, $user);
        $spend = $this->spendRepository->find($id);
        if (!$spend instanceof Spend) {
            return $this->json(['success' => false, 'error' => 'Spend not found.'], 404);
        }

        $draft = $this->dashboardControllerService->createSpendDraftFromSpend($spend);
        $plans = $this->dashboardControllerService->getSpendPlanChoicesForDate($draft->getSpendDate());

        return $this->json([
            'success' => true,
            'payload' => [
                'spend' => $this->serializeSpendItem($this->dashboardControllerService->mapSpendToItemDto($spend)),
                'form' => $this->buildSpendFormShape($draft, $plans),
            ],
        ]);
    }

    #[Route('/spends/{id}', name: 'spends_update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    public function updateSpend(int $id, Request $request): JsonResponse
    {
        $user = $this->requireTelegramMiniUser($request);
        if (!$user instanceof User) {
            return $this->miniDenied();
        }

        $this->applyUserLocale($request, $user);
        $spend = $this->spendRepository->find($id);
        if (!$spend instanceof Spend) {
            return $this->json(['success' => false, 'error' => 'Spend not found.'], 404);
        }

        $payload = $this->decodeJsonPayload($request);
        $draft = $this->dashboardControllerService->createSpendDraftFromSpend($spend);
        $this->applySpendDraftFromPayload($draft, $payload, $spend->getSpendDate());
        $result = $this->dashboardControllerService->updateSpend($spend, $draft);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'error' => $result->errorMessage ?? $this->translator->trans('spend.unable_update'),
            ], 422);
        }

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('spend.updated'),
        ]);
    }

    #[Route('/spends/{id}', name: 'spends_delete', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    public function deleteSpend(int $id, Request $request): JsonResponse
    {
        $user = $this->requireTelegramMiniUser($request);
        if (!$user instanceof User) {
            return $this->miniDenied();
        }

        $this->applyUserLocale($request, $user);
        $spend = $this->spendRepository->find($id);
        if (!$spend instanceof Spend) {
            return $this->json(['success' => false, 'error' => 'Spend not found.'], 404);
        }

        $this->dashboardControllerService->deleteSpend($spend);

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('spend.deleted'),
        ]);
    }

    private function requireTelegramMiniUser(Request $request): ?User
    {
        return $this->telegramMiniUserResolver->resolveUser($request);
    }

    private function miniDenied(): JsonResponse
    {
        return $this->json([
            'success' => false,
            'error' => 'Invalid mini-app token.',
        ], 403);
    }

    private function applyUserLocale(Request $request, User $user): void
    {
        $language = $this->userMetadataService->getPreferences($user)['language'] ?? 'en';
        if (!is_string($language) || !in_array($language, ['en', 'ru'], true)) {
            $language = 'en';
        }

        $request->setLocale($language);
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
        $draft = $this->dashboardControllerService->createSpendDraft($now);

        if (array_key_exists('amount', $payload)) {
            $draft->setAmount((string) $payload['amount']);
        }

        if (array_key_exists('currencyId', $payload)) {
            $draft->setCurrency($this->resolveCurrency($payload['currencyId']));
        }

        if (array_key_exists('spendingPlanId', $payload)) {
            $draft->setSpendingPlan($this->resolveSpendingPlan($payload['spendingPlanId']));
        }

        if (array_key_exists('spendDate', $payload) && is_string($payload['spendDate'])) {
            $dateValue = trim($payload['spendDate']);
            if ('' !== $dateValue) {
                try {
                    $draft->setSpendDate(new \DateTimeImmutable($dateValue));
                } catch (\Exception) {
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
    private function applySpendDraftFromPayload(DashboardSpendDraftDto $draft, array $payload, \DateTimeImmutable $fallbackDate): void
    {
        if (array_key_exists('amount', $payload)) {
            $draft->setAmount((string) $payload['amount']);
        }

        if (array_key_exists('currencyId', $payload)) {
            $draft->setCurrency($this->resolveCurrency($payload['currencyId']));
        }

        if (array_key_exists('spendingPlanId', $payload)) {
            $draft->setSpendingPlan($this->resolveSpendingPlan($payload['spendingPlanId']));
        }

        if (array_key_exists('spendDate', $payload) && is_string($payload['spendDate'])) {
            $dateValue = trim($payload['spendDate']);
            if ('' !== $dateValue) {
                try {
                    $draft->setSpendDate(new \DateTimeImmutable($dateValue));
                } catch (\Exception) {
                    $draft->setSpendDate($fallbackDate);
                }
            }
        }

        if (array_key_exists('comment', $payload)) {
            $comment = $payload['comment'];
            $draft->setComment(is_string($comment) ? $comment : null);
        }
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

    private function serializeSpendItem(DashboardSpendItemDto $item): array
    {
        return [
            'id' => $item->id,
            'username' => $item->username,
            'amount' => $item->amount,
            'currencyCode' => $item->currencyCode,
            'spendingPlanName' => $item->spendingPlanName,
            'spendDateLabel' => $item->spendDateLabel,
            'comment' => $item->comment,
            'createdAtLabel' => $item->createdAtLabel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSpendListPage(DashboardSpendListPageDto $dto): array
    {
        $data = $dto->toArray();
        $data['spends'] = array_map(fn (DashboardSpendItemDto $item): array => $this->serializeSpendItem($item), $dto->spends);

        $streamGroups = [];
        foreach ($dto->streamGroups as $group) {
            $spends = [];
            foreach ($group['spends'] as $item) {
                if ($item instanceof DashboardSpendItemDto) {
                    $spends[] = $this->serializeSpendItem($item);
                }
            }

            $streamGroups[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'totalAmountLabel' => $group['totalAmountLabel'],
                'plannedAmountLabel' => $group['plannedAmountLabel'],
                'current' => $group['current'],
                'expanded' => $group['expanded'],
                'spends' => $spends,
            ];
        }

        $data['streamGroups'] = $streamGroups;

        return $data;
    }

    /**
     * @param list<SpendingPlan> $plans
     *
     * @return array<string, mixed>
     */
    private function buildSpendFormShape(DashboardSpendDraftDto $draft, array $plans): array
    {
        $currencies = $this->currencyRepository->findBy([], ['code' => 'ASC']);
        $currencyOptions = array_map(
            static fn (Currency $currency): array => [
                'id' => (int) $currency->getId(),
                'code' => $currency->getCode(),
            ],
            $currencies
        );

        $spendingPlanOptions = array_map(
            static fn (SpendingPlan $spendingPlan): array => [
                'id' => (int) $spendingPlan->getId(),
                'name' => $spendingPlan->getName(),
                'dateFrom' => $spendingPlan->getDateFrom()->format('Y-m-d'),
                'dateTo' => $spendingPlan->getDateTo()->format('Y-m-d'),
            ],
            $plans
        );

        return [
            'defaults' => [
                'amount' => $draft->getAmount(),
                'currencyId' => (int) ($draft->getCurrency()?->getId() ?? 0),
                'spendingPlanId' => (int) ($draft->getSpendingPlan()?->getId() ?? 0),
                'spendDate' => $draft->getSpendDate()->format('Y-m-d'),
                'comment' => $draft->getComment() ?? '',
            ],
            'currencies' => $currencyOptions,
            'spendingPlans' => $spendingPlanOptions,
        ];
    }
}
