<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\Notification\NotificationActionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/notifications')]
final class AdminNotificationController extends AbstractController
{
    #[Route('/action', name: 'admin_notifications_action', methods: ['POST'])]
    public function action(
        Request $request,
        NotificationActionService $notificationActionService,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('admin_notification_action', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Admin user required.');
        }

        $templateCode = (string) $request->request->get('template', '');
        $monthKey = (string) $request->request->get('monthKey', '');
        $actionCode = (string) $request->request->get('action', '');
        $redirect = (string) $request->request->get('_redirect', '');

        $applied = $notificationActionService->applyAction(
            $user,
            $templateCode,
            $monthKey,
            $actionCode,
            new \DateTimeImmutable()
        );

        if (!$applied) {
            $this->addFlash('error', 'Unable to apply notification action.');
        }

        if ('' !== $redirect && str_starts_with($redirect, '/')) {
            return $this->redirect($redirect);
        }

        return $this->redirectToRoute('admin_dashboard');
    }
}
