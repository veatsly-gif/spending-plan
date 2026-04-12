<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Income;
use App\Entity\User;
use App\Form\Web\DashboardIncomeType;
use App\Form\Web\DashboardSpendType;
use App\Repository\IncomeRepository;
use App\Repository\SpendRepository;
use App\Service\Controller\Web\DashboardControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardControllerService $service,
        private readonly IncomeRepository $incomeRepository,
        private readonly SpendRepository $spendRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        return $this->redirectToSpa('dashboard', $request->query->all());
    }

    #[Route('/dashboard/incomes', name: 'app_dashboard_incomes', methods: ['GET'])]
    public function incomes(Request $request): Response
    {
        return $this->redirectToSpa('dashboard/incomes', $request->query->all());
    }

    #[Route('/dashboard/incomes/create', name: 'app_dashboard_incomes_create', methods: ['POST'])]
    public function createIncome(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User is not authenticated.');
        }

        if (!$this->isGranted('ROLE_INCOMER')) {
            throw $this->createAccessDeniedException('Income creation is allowed for incomer role only.');
        }

        $draft = $this->service->createIncomeDraft();
        $form = $this->createForm(DashboardIncomeType::class, $draft);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $this->collectFirstFormError($form) ?? $this->translator->trans('income.form_invalid'),
                ], 422);
            }

            $this->addFlash('error', $this->collectFirstFormError($form) ?? 'income.form_invalid');

            return $this->redirect($this->resolveIncomeRedirectPath($request));
        }

        $result = $this->service->createIncome($user, $draft);
        if (!$result->success) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $result->errorMessage ?? $this->translator->trans('income.unable_create'),
                ], 422);
            }

            $this->addFlash('error', $result->errorMessage ?? 'income.unable_create');

            return $this->redirect($this->resolveIncomeRedirectPath($request));
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('flash.income_added'),
            ]);
        }

        $this->addFlash('success', 'flash.income_added');

        return $this->redirect($this->resolveIncomeRedirectPath($request));
    }

    #[Route('/dashboard/incomes/{id}/edit', name: 'app_dashboard_incomes_edit', methods: ['GET', 'POST'])]
    public function editIncome(int $id, Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToSpa('dashboard/incomes/'.$id.'/edit');
        }

        if (!$this->isGranted('ROLE_INCOMER')) {
            throw $this->createAccessDeniedException('Income edit is allowed for incomer role only.');
        }

        $income = $this->incomeRepository->find($id);
        if (!$income instanceof Income) {
            throw $this->createNotFoundException('Income not found.');
        }

        $draft = $this->service->createIncomeDraftFromIncome($income);
        $form = $this->createForm(DashboardIncomeType::class, $draft);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->service->updateIncome($income, $draft);
            if ($result->success) {
                $this->addFlash('success', 'income.updated');

                return $this->redirectToRoute('app_dashboard_incomes', [
                    'month' => $income->getCreatedAt()->format('Y-m'),
                ]);
            }

            $form->get('amount')->addError(new FormError($result->errorMessage ?? 'income.unable_update'));
        }

        return $this->redirectToSpa('dashboard/incomes/'.$id.'/edit');
    }

    #[Route('/dashboard/incomes/{id}/delete', name: 'app_dashboard_incomes_delete', methods: ['POST'])]
    public function deleteIncome(int $id, Request $request): Response
    {
        if (!$this->isGranted('ROLE_INCOMER')) {
            throw $this->createAccessDeniedException('Income delete is allowed for incomer role only.');
        }

        $income = $this->incomeRepository->find($id);
        if (!$income instanceof Income) {
            throw $this->createNotFoundException('Income not found.');
        }

        if (!$this->isCsrfTokenValid('delete_income_'.$income->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
        } else {
            $month = $income->getCreatedAt()->format('Y-m');
            $this->service->deleteIncome($income);
            $this->addFlash('success', 'income.deleted');

            return $this->redirectToRoute('app_dashboard_incomes', ['month' => $month]);
        }

        return $this->redirectToRoute('app_dashboard_incomes');
    }

    #[Route('/dashboard/spends', name: 'app_dashboard_spends', methods: ['GET'])]
    public function spends(Request $request): Response
    {
        return $this->redirectToSpa('dashboard/spends', $request->query->all());
    }

    #[Route('/dashboard/spends/version', name: 'app_dashboard_spends_version', methods: ['GET'])]
    public function spendsVersion(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User is not authenticated.');
        }

        $month = $request->query->get('month');
        $month = is_string($month) ? $month : null;

        return new JsonResponse(
            $this->service->buildSpendListVersionData($month, new \DateTimeImmutable())
        );
    }

    #[Route('/dashboard/spends/create', name: 'app_dashboard_spends_create', methods: ['POST'])]
    public function createSpend(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User is not authenticated.');
        }

        $draft = $this->service->createSpendDraft(new \DateTimeImmutable());
        $form = $this->createForm(DashboardSpendType::class, $draft, [
            'spending_plan_choices' => $this->service->getSpendPlanChoicesForDate($draft->getSpendDate()),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $this->collectFirstFormError($form) ?? $this->translator->trans('spend.form_invalid'),
                ], 422);
            }

            $this->addFlash('error', $this->collectFirstFormError($form) ?? 'spend.form_invalid');

            return $this->redirectToRoute('app_dashboard');
        }

        $result = $this->service->createSpend($user, $draft);
        if (!$result->success) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $result->errorMessage ?? $this->translator->trans('spend.unable_create'),
                ], 422);
            }

            $this->addFlash('error', $result->errorMessage ?? 'spend.unable_create');

            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isXmlHttpRequest()) {
            $defaultDraft = $this->service->createSpendDraft(new \DateTimeImmutable());
            $viewData = $this->service->buildViewData($user, new \DateTimeImmutable());
            $spendWidget = $viewData->spendWidget;

            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('flash.spend_added'),
                'defaults' => [
                    'amount' => '',
                    'currencyId' => (string) ($defaultDraft->getCurrency()?->getId() ?? ''),
                    'spendingPlanId' => (string) ($defaultDraft->getSpendingPlan()?->getId() ?? ''),
                    'spendDate' => $defaultDraft->getSpendDate()->format('Y-m-d'),
                    'comment' => '',
                ],
                'widget' => [
                    'currentTimePlanName' => $spendWidget->currentTimePlanName,
                    'currentTimePlanSpentGel' => $spendWidget->currentTimePlanSpentGel,
                    'currentTimePlanLimitGel' => $spendWidget->currentTimePlanLimitGel,
                    'currentTimePlanProgressPercent' => $spendWidget->currentTimePlanProgressPercent,
                    'currentTimePlanProgressBarPercent' => $spendWidget->currentTimePlanProgressBarPercent,
                    'currentTimePlanProgressTone' => $spendWidget->currentTimePlanProgressTone,
                    'monthSpentGel' => $spendWidget->monthSpentGel,
                    'monthLimitGel' => $spendWidget->monthLimitGel,
                    'progressPercent' => $spendWidget->monthSpendProgressPercent,
                    'progressBarPercent' => $spendWidget->monthSpendProgressBarPercent,
                    'progressTone' => $spendWidget->monthSpendProgressTone,
                    'todaySpentGel' => $spendWidget->todaySpentGel,
                    'recentSpends' => array_map(
                        static fn (\App\DTO\Controller\Web\DashboardSpendItemDto $item): array => [
                            'amount' => $item->amount,
                            'currencyCode' => $item->currencyCode,
                            'datetime' => $item->createdAtLabel,
                            'username' => $item->username,
                            'description' => trim((string) $item->comment),
                        ],
                        $spendWidget->recentSpends
                    ),
                ],
            ]);
        }

        $this->addFlash('success', 'flash.spend_added');

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/spends/{id}/edit', name: 'app_dashboard_spends_edit', methods: ['GET', 'POST'])]
    public function editSpend(int $id, Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToSpa('dashboard/spends/'.$id.'/edit');
        }

        $spend = $this->spendRepository->find($id);
        if (!$spend instanceof \App\Entity\Spend) {
            throw $this->createNotFoundException('Spend not found.');
        }

        $draft = $this->service->createSpendDraftFromSpend($spend);
        $form = $this->createForm(DashboardSpendType::class, $draft, [
            'spending_plan_choices' => $this->service->getSpendPlanChoicesForDate($draft->getSpendDate()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->service->updateSpend($spend, $draft);
            if ($result->success) {
                $this->addFlash('success', 'spend.updated');

                return $this->redirectToRoute('app_dashboard_spends', [
                    'month' => $spend->getSpendDate()->format('Y-m'),
                ]);
            }

            $normalized = mb_strtolower((string) ($result->errorMessage ?? ''));
            if (str_contains($normalized, 'spend date')) {
                $form->get('spendDate')->addError(new FormError($result->errorMessage ?? 'spend.unable_update'));
            } else {
                $form->get('amount')->addError(new FormError($result->errorMessage ?? 'spend.unable_update'));
            }
        }

        return $this->redirectToSpa('dashboard/spends/'.$id.'/edit');
    }

    #[Route('/dashboard/spends/{id}/delete', name: 'app_dashboard_spends_delete', methods: ['POST'])]
    public function deleteSpend(int $id, Request $request): Response
    {
        $spend = $this->spendRepository->find($id);
        if (!$spend instanceof \App\Entity\Spend) {
            throw $this->createNotFoundException('Spend not found.');
        }

        if (!$this->isCsrfTokenValid('delete_spend_'.$spend->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
        } else {
            $month = $spend->getSpendDate()->format('Y-m');
            $this->service->deleteSpend($spend);
            $this->addFlash('success', 'spend.deleted');

            return $this->redirectToRoute('app_dashboard_spends', ['month' => $month]);
        }

        return $this->redirectToRoute('app_dashboard_spends');
    }

    /**
     * @param array<string, mixed> $query
     */
    private function redirectToSpa(string $path, array $query = []): Response
    {
        $url = $this->generateUrl('web_spa_entry', ['path' => $path]);
        if ([] !== $query) {
            $url .= '?'.http_build_query($query);
        }

        return $this->redirect($url);
    }

    private function collectFirstFormError(FormInterface $form): ?string
    {
        foreach ($form->getErrors(true) as $error) {
            return $error->getMessage();
        }

        return null;
    }

    private function resolveIncomeRedirectPath(Request $request): string
    {
        $target = $request->request->get('_redirect');
        $target = is_string($target) ? trim($target) : '';
        if ('' !== $target && str_starts_with($target, '/')) {
            return $target;
        }

        return $this->generateUrl('app_dashboard');
    }
}
