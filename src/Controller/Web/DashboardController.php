<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Form\Web\DashboardIncomeType;
use App\Form\Web\DashboardSpendType;
use App\Service\Controller\Web\DashboardControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardControllerService $service,
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
                    'error' => $this->collectFirstFormError($form) ?? $this->trans('spend.form_invalid'),
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
                    'error' => $result->errorMessage ?? $this->trans('spend.unable_create'),
                ], 422);
            }

            $this->addFlash('error', $result->errorMessage ?? 'spend.unable_create');

            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isXmlHttpRequest()) {
            $defaultDraft = $this->service->createSpendDraft(new \DateTimeImmutable());
            $viewData = $this->service->buildViewData($user, new \DateTimeImmutable());
            $spendWidget = $viewData->spendWidget;
            $lastSpend = $spendWidget->lastSpend;

            return new JsonResponse([
                'success' => true,
                'message' => $this->trans('flash.spend_added'),
                'defaults' => [
                    'amount' => '',
                    'currencyId' => (string) ($defaultDraft->getCurrency()?->getId() ?? ''),
                    'spendingPlanId' => (string) ($defaultDraft->getSpendingPlan()?->getId() ?? ''),
                    'spendDate' => $defaultDraft->getSpendDate()->format('Y-m-d'),
                    'comment' => '',
                ],
                'widget' => [
                    'count' => $spendWidget->monthSpendCount,
                    'total' => $spendWidget->monthSpendAmountLabel,
                    'lastLabel' => null !== $lastSpend
                        ? $this->trans('dashboard.last_spend_value', [
                            '%amount%' => $lastSpend->amount,
                            '%currency%' => $lastSpend->currencyCode,
                            '%date%' => $lastSpend->spendDateLabel,
                        ])
                        : $this->trans('dashboard.last_spend_na'),
                ],
            ]);
        }

        $this->addFlash('success', 'flash.spend_added');

        return $this->redirectToRoute('app_dashboard');
    }

    private function collectFirstFormError(FormInterface $form): ?string
    {
        foreach ($form->getErrors(true) as $error) {
            return $error->getMessage();
        }

        return null;
    }
}
