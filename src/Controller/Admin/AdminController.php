<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SpendingPlanRepository;
use App\Repository\TelegramUserRepository;
use App\Service\Controller\Admin\AdminDashboardControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    ): RedirectResponse
    {
        // Redirect to spending plans as the default admin page
        return $this->redirectToRoute('admin_spending_plans_index');
    }
}
