<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\TelegramUserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function __invoke(TelegramUserRepository $telegramUserRepository): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'pendingTelegramUsers' => $telegramUserRepository->countPending(),
        ]);
    }
}
