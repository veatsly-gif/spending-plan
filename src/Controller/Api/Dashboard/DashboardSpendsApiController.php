<?php

declare(strict_types=1);

namespace App\Controller\Api\Dashboard;

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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/dashboard/spends', name: 'api_dashboard_spends_')]
#[IsGranted('ROLE_USER')]
final class DashboardSpendsApiController extends AbstractController
{
    public function __construct(
        private readonly DashboardControllerService $service,
        private readonly CurrencyRepository $currencyRepository,
        private readonly SpendingPlanRepository $spendingPlanRepository,
        private readonly SpendRepository $spendRepository,
        private readonly TranslatorInterface $translator,
        private readonly DashboardOverviewPayloadFactory $overviewPayloadFactory,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->requireUser();
        $dto = $this->service->buildSpendListViewData($request->query->all(), new \DateTimeImmutable());

        return $this->json([
            'success' => true,
            'payload' => $this->serializeSpendListPage($dto),
        ]);
    }

    #[Route('/version', name: 'version', methods: ['GET'])]
    public function version(Request $request): JsonResponse
    {
        $this->requireUser();
        $month = $request->query->get('month');
        $month = is_string($month) ? $month : null;

        return $this->json([
            'success' => true,
            'payload' => $this->service->buildSpendListVersionData($month, new \DateTimeImmutable()),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
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
            'payload' => $this->overviewPayloadFactory->build($user),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $this->requireUser();
        $spend = $this->spendRepository->find($id);
        if (!$spend instanceof Spend) {
            return $this->json(['success' => false, 'error' => 'Spend not found.'], 404);
        }

        $draft = $this->service->createSpendDraftFromSpend($spend);
        $plans = $this->service->getSpendPlanChoicesForDate($draft->getSpendDate());

        return $this->json([
            'success' => true,
            'payload' => [
                'spend' => $this->serializeSpendItem($this->service->mapSpendToItemDto($spend)),
                'form' => $this->buildSpendFormShape($draft, $plans),
            ],
        ]);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $spend = $this->spendRepository->find($id);
        if (!$spend instanceof Spend) {
            return $this->json(['success' => false, 'error' => 'Spend not found.'], 404);
        }

        $payload = $this->decodeJsonPayload($request);
        $draft = $this->service->createSpendDraftFromSpend($spend);
        $this->applySpendDraftFromPayload($draft, $payload, $spend->getSpendDate());
        $result = $this->service->updateSpend($spend, $draft);

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

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $spend = $this->spendRepository->find($id);
        if (!$spend instanceof Spend) {
            return $this->json(['success' => false, 'error' => 'Spend not found.'], 404);
        }

        $this->service->deleteSpend($spend);

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('spend.deleted'),
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
