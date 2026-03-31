<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Form\Web\DashboardIncomeType;
use App\Form\Web\DashboardSpendType;
use App\Repository\SpendRepository;
use App\Service\Controller\Web\DashboardControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        private readonly SpendRepository $spendRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User is not authenticated.');
        }

        $spendDraft = $this->service->createSpendDraft(new \DateTimeImmutable());
        $spendForm = $this->createForm(DashboardSpendType::class, $spendDraft, [
            'action' => $this->generateUrl('app_dashboard_spends_create'),
            'spending_plan_choices' => $this->service->getSpendPlanChoicesForDate($spendDraft->getSpendDate()),
        ]);

        $incomeForm = null;
        if (in_array('ROLE_INCOMER', $user->getRoles(), true)) {
            $incomeDraft = $this->service->createIncomeDraft();
            $incomeForm = $this->createForm(DashboardIncomeType::class, $incomeDraft);
            $incomeForm->handleRequest($request);

            if ($incomeForm->isSubmitted() && $incomeForm->isValid()) {
                $result = $this->service->createIncome($user, $incomeDraft);
                if (!$result->success) {
                    $this->addFlash(
                        'error',
                        $result->errorMessage ?? 'flash.unable_create_income'
                    );
                } else {
                    $this->addFlash('success', 'flash.income_added');
                }

                return $this->redirectToRoute('app_dashboard');
            }
        }

        $dto = $this->service->buildViewData($user, new \DateTimeImmutable());
        $context = $dto->toArray();
        $context['spendForm'] = $spendForm->createView();
        if (null !== $incomeForm) {
            $context['incomeForm'] = $incomeForm->createView();
        }

        return $this->render('dashboard/index.html.twig', $context);
    }

    #[Route('/dashboard/incomes', name: 'app_dashboard_incomes', methods: ['GET'])]
    public function incomes(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User is not authenticated.');
        }

        $dto = $this->service->buildIncomeListViewData(new \DateTimeImmutable());

        return $this->render('dashboard/incomes.html.twig', $dto->toArray());
    }

    #[Route('/dashboard/spends', name: 'app_dashboard_spends', methods: ['GET'])]
    public function spends(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User is not authenticated.');
        }

        $dto = $this->service->buildSpendListViewData($request->query->all(), new \DateTimeImmutable());

        return $this->render('dashboard/spends.html.twig', $dto->toArray());
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

            $this->addFlash('error', $result->errorMessage ?? 'spend.unable_update');
        }

        return $this->render('dashboard/spend_edit.html.twig', [
            'form' => $form->createView(),
            'spend' => $spend,
        ]);
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

    private function collectFirstFormError(FormInterface $form): ?string
    {
        foreach ($form->getErrors(true) as $error) {
            return $error->getMessage();
        }

        return null;
    }
}
