<?php

declare(strict_types=1);

namespace App\Controller\Api\Dashboard;

use App\DTO\Controller\Web\DashboardIncomeDraftDto;
use App\DTO\Controller\Web\DashboardIncomeItemDto;
use App\DTO\Controller\Web\DashboardIncomeListPageDto;
use App\Entity\Currency;
use App\Entity\Income;
use App\Entity\User;
use App\Repository\CurrencyRepository;
use App\Repository\IncomeRepository;
use App\Service\Api\DashboardOverviewPayloadFactory;
use App\Service\Controller\Web\DashboardControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/dashboard/incomes', name: 'api_dashboard_incomes_')]
#[IsGranted('ROLE_USER')]
final class DashboardIncomesApiController extends AbstractController
{
    public function __construct(
        private readonly DashboardControllerService $service,
        private readonly CurrencyRepository $currencyRepository,
        private readonly IncomeRepository $incomeRepository,
        private readonly TranslatorInterface $translator,
        private readonly DashboardOverviewPayloadFactory $overviewPayloadFactory,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->requireUser();
        $dto = $this->service->buildIncomeListViewData($request->query->all(), new \DateTimeImmutable());

        return $this->json([
            'success' => true,
            'payload' => $this->serializeIncomeListPage($dto),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
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
            'payload' => $this->overviewPayloadFactory->build($user),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $this->requireUser();
        if (!$this->isGranted('ROLE_INCOMER')) {
            return $this->json(['success' => false, 'error' => 'Forbidden.'], 403);
        }

        $income = $this->incomeRepository->find($id);
        if (!$income instanceof Income) {
            return $this->json(['success' => false, 'error' => 'Income not found.'], 404);
        }

        $draft = $this->service->createIncomeDraftFromIncome($income);

        return $this->json([
            'success' => true,
            'payload' => [
                'income' => $this->serializeIncomeItem($this->service->mapIncomeToItemDto($income)),
                'form' => $this->buildIncomeFormShape($draft),
            ],
        ]);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => '\\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_INCOMER')) {
            return $this->json(['success' => false, 'error' => 'Forbidden.'], 403);
        }

        $income = $this->incomeRepository->find($id);
        if (!$income instanceof Income) {
            return $this->json(['success' => false, 'error' => 'Income not found.'], 404);
        }

        $payload = $this->decodeJsonPayload($request);
        $draft = $this->service->createIncomeDraftFromIncome($income);
        $this->applyIncomeDraftFromPayload($draft, $payload);
        $result = $this->service->updateIncome($income, $draft);

        if (!$result->success) {
            return $this->json([
                'success' => false,
                'error' => $result->errorMessage ?? $this->translator->trans('income.unable_update'),
            ], 422);
        }

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('income.updated'),
        ]);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        if (!$this->isGranted('ROLE_INCOMER')) {
            return $this->json(['success' => false, 'error' => 'Forbidden.'], 403);
        }

        $income = $this->incomeRepository->find($id);
        if (!$income instanceof Income) {
            return $this->json(['success' => false, 'error' => 'Income not found.'], 404);
        }

        $this->service->deleteIncome($income);

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('income.deleted'),
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
    private function hydrateIncomeDraft(array $payload): DashboardIncomeDraftDto
    {
        $draft = $this->service->createIncomeDraft();

        if (array_key_exists('amount', $payload)) {
            $draft->setAmount((string) $payload['amount']);
        }

        if (array_key_exists('currencyId', $payload)) {
            $draft->setCurrency($this->resolveCurrency($payload['currencyId']));
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

    /**
     * @param array<string, mixed> $payload
     */
    private function applyIncomeDraftFromPayload(DashboardIncomeDraftDto $draft, array $payload): void
    {
        if (array_key_exists('amount', $payload)) {
            $draft->setAmount((string) $payload['amount']);
        }

        if (array_key_exists('currencyId', $payload)) {
            $draft->setCurrency($this->resolveCurrency($payload['currencyId']));
        }

        if (array_key_exists('comment', $payload)) {
            $comment = $payload['comment'];
            $draft->setComment(is_string($comment) ? $comment : null);
        }

        if (array_key_exists('convertToGel', $payload)) {
            $draft->setConvertToGel((bool) $payload['convertToGel']);
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

    private function serializeIncomeItem(DashboardIncomeItemDto $item): array
    {
        return [
            'id' => $item->id,
            'username' => $item->username,
            'amount' => $item->amount,
            'currencyCode' => $item->currencyCode,
            'amountInGel' => $item->amountInGel,
            'officialRatedAmountInGel' => $item->officialRatedAmountInGel,
            'rate' => $item->rate,
            'comment' => $item->comment,
            'createdAtLabel' => $item->createdAtLabel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIncomeListPage(DashboardIncomeListPageDto $dto): array
    {
        $data = $dto->toArray();
        $data['incomes'] = array_map(fn (DashboardIncomeItemDto $item): array => $this->serializeIncomeItem($item), $dto->incomes);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIncomeFormShape(DashboardIncomeDraftDto $draft): array
    {
        $currencies = $this->currencyRepository->findBy([], ['code' => 'ASC']);
        $currencyOptions = array_map(
            static fn (Currency $currency): array => [
                'id' => (int) $currency->getId(),
                'code' => $currency->getCode(),
            ],
            $currencies
        );

        return [
            'defaults' => [
                'amount' => $draft->getAmount(),
                'currencyId' => (int) ($draft->getCurrency()?->getId() ?? 0),
                'comment' => $draft->getComment() ?? '',
                'convertToGel' => $draft->isConvertToGel(),
            ],
            'currencies' => $currencyOptions,
        ];
    }
}
