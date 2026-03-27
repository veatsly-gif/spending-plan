<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SpendingPlan;
use App\Form\Admin\AdminSpendingPlanType;
use App\Repository\SpendingPlanRepository;
use App\Service\Controller\Admin\AdminSpendingPlanControllerService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/spending-plans')]
final class AdminSpendingPlanController extends AbstractController
{
    public function __construct(
        private readonly AdminSpendingPlanControllerService $service,
    ) {
    }

    #[Route('', name: 'admin_spending_plans_index', methods: ['GET'])]
    public function index(Request $request, SpendingPlanRepository $spendingPlanRepository): Response
    {
        $dto = $this->service->buildIndexViewData(
            $request->query->get('month'),
            $spendingPlanRepository
        );

        return $this->render('admin/spending_plans/index.html.twig', $dto->toArray());
    }

    #[Route('/new', name: 'admin_spending_plans_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SpendingPlanRepository $spendingPlanRepository): Response
    {
        $draftDto = $this->service->createDraftSpendingPlan();
        $spendingPlan = $draftDto->spendingPlan;

        $form = $this->createForm(AdminSpendingPlanType::class, $spendingPlan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->service->createSpendingPlan($spendingPlan, $spendingPlanRepository);
            if (!$result->success) {
                $this->addFlash('error', $result->errorMessage ?? 'Unable to create spending plan.');

                return $this->redirectToRoute('admin_spending_plans_index');
            }

            $this->addFlash('success', 'Spending plan created.');

            return $this->redirectToRoute('admin_spending_plans_index', [
                'month' => $spendingPlan->getDateFrom()->format('Y-m'),
            ]);
        }

        return $this->render('admin/spending_plans/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_spending_plans_edit', methods: ['GET', 'POST'])]
    public function edit(
        SpendingPlan $spendingPlan,
        Request $request,
        SpendingPlanRepository $spendingPlanRepository,
    ): Response
    {
        $form = $this->createForm(AdminSpendingPlanType::class, $spendingPlan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->service->updateSpendingPlan($spendingPlan, $spendingPlanRepository);
            if (!$result->success) {
                $this->addFlash('error', $result->errorMessage ?? 'Unable to update spending plan.');

                return $this->redirectToRoute('admin_spending_plans_index');
            }

            $this->addFlash('success', 'Spending plan updated.');

            return $this->redirectToRoute('admin_spending_plans_index', [
                'month' => $spendingPlan->getDateFrom()->format('Y-m'),
            ]);
        }

        return $this->render('admin/spending_plans/edit.html.twig', [
            'spendingPlan' => $spendingPlan,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_spending_plans_delete', methods: ['POST'])]
    public function delete(
        SpendingPlan $spendingPlan,
        Request $request,
        SpendingPlanRepository $spendingPlanRepository,
    ): Response
    {
        if (
            !$this->isCsrfTokenValid(
                'delete_spending_plan_'.$spendingPlan->getId(),
                (string) $request->request->get('_token')
            )
        ) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $result = $this->service->removeSpendingPlan($spendingPlan, $spendingPlanRepository);
        if (!$result->success) {
            $this->addFlash('error', $result->errorMessage ?? 'Unable to remove spending plan.');

            return $this->redirectToRoute('admin_spending_plans_index');
        }

        $this->addFlash('success', 'Spending plan removed.');

        return $this->redirectToRoute('admin_spending_plans_index', [
            'month' => $spendingPlan->getDateFrom()->format('Y-m'),
        ]);
    }

    #[Route(
        '/suggestions/{month}/{suggestionId}/approve',
        name: 'admin_spending_plans_suggestion_approve',
        methods: ['POST']
    )]
    public function approveSuggestion(
        string $month,
        string $suggestionId,
        Request $request,
        SpendingPlanRepository $spendingPlanRepository,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('sp_suggestion_action', (string) $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid CSRF token.'], 403);
        }

        $limitAmount = (string) $request->request->get('limitAmount', '');
        $currencyCode = $request->request->get('currencyCode');
        $currencyCode = is_string($currencyCode) ? $currencyCode : null;
        $weight = $request->request->get('weight');
        $weight = is_scalar($weight) ? (string) $weight : null;
        $note = $request->request->get('note');
        $note = is_scalar($note) ? (string) $note : null;
        $result = $this->service->approveSuggestion(
            $month,
            $suggestionId,
            $limitAmount,
            $currencyCode,
            $weight,
            $note,
            $spendingPlanRepository
        );

        if (!$result->success || null === $result->spendingPlan) {
            return new JsonResponse([
                'success' => false,
                'error' => $result->errorMessage ?? 'Unable to approve suggestion.',
            ], 400);
        }

        $html = $this->renderView('admin/spending_plans/_existing_card.html.twig', [
            'plan' => $result->spendingPlan,
        ]);

        return new JsonResponse([
            'success' => true,
            'existingHtml' => $html,
        ]);
    }

    #[Route(
        '/suggestions/{month}/{suggestionId}/delete',
        name: 'admin_spending_plans_suggestion_delete',
        methods: ['POST']
    )]
    public function deleteSuggestion(string $month, string $suggestionId, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('sp_suggestion_action', (string) $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid CSRF token.'], 403);
        }

        $result = $this->service->deleteSuggestion($month, $suggestionId);
        if (!$result->success) {
            return new JsonResponse([
                'success' => false,
                'error' => $result->errorMessage ?? 'Unable to remove suggestion.',
            ], 400);
        }

        return new JsonResponse(['success' => true]);
    }
}
