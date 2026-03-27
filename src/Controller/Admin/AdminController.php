<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SpendingPlanRepository;
use App\Repository\TelegramUserRepository;
use App\Service\Controller\Admin\AdminDashboardControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly AdminDashboardControllerService $service,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function __invoke(
        TelegramUserRepository $telegramUserRepository,
        SpendingPlanRepository $spendingPlanRepository,
    ): Response
    {
        $dto = $this->service->buildViewData(
            $telegramUserRepository,
            $spendingPlanRepository
        );

        return $this->render('admin/dashboard.html.twig', $dto->toArray());
    }
}
