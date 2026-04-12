<?php

declare(strict_types=1);

namespace App\Controller\Api\Dashboard;

use App\Entity\User;
use App\Service\Api\DashboardOverviewPayloadFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard', name: 'api_dashboard_')]
#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardOverviewPayloadFactory $overviewPayloadFactory,
    ) {
    }

    #[Route('', name: 'overview', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        $user = $this->requireUser();

        return $this->json([
            'success' => true,
            'payload' => $this->overviewPayloadFactory->build($user),
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
}
