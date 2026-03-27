<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Form\Web\DashboardIncomeType;
use App\Service\Controller\Web\DashboardControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
                        $result->errorMessage ?? 'Unable to create income.'
                    );
                } else {
                    $this->addFlash('success', 'Income added.');
                }

                return $this->redirectToRoute('app_dashboard');
            }
        }

        $dto = $this->service->buildViewData($user, new \DateTimeImmutable());
        $context = $dto->toArray();
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
}
