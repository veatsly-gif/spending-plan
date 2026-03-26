<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TelegramUser;
use App\Entity\User;
use App\Form\Admin\TelegramApproveType;
use App\Form\Admin\TelegramCreateUserType;
use App\Repository\TelegramUserRepository;
use App\Repository\UserRepository;
use App\Service\Controller\Admin\AdminTelegramControllerService;
use App\Service\TelegramBotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/telegram')]
final class AdminTelegramController extends AbstractController
{
    public function __construct(
        private readonly AdminTelegramControllerService $service,
    ) {
    }

    #[Route('', name: 'admin_telegram_pending', methods: ['GET'])]
    public function index(TelegramUserRepository $telegramUserRepository): Response
    {
        $dto = $this->service->buildPendingViewData($telegramUserRepository);

        return $this->render('admin/telegram/pending.html.twig', $dto->toArray());
    }

    #[Route('/{id}/approve', name: 'admin_telegram_approve', methods: ['GET', 'POST'])]
    public function approve(
        TelegramUser $telegramUser,
        Request $request,
        TelegramUserRepository $telegramUserRepository,
        TelegramBotService $telegramBotService,
    ): Response {
        $decision = $this->service->shouldRedirectApproved($telegramUser);
        if ($decision->value) {
            return $this->redirectToRoute('admin_telegram_pending');
        }

        $form = $this->createForm(TelegramApproveType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedUser = $form->get('user')->getData();
            if (!$selectedUser instanceof User) {
                throw $this->createNotFoundException('User is required.');
            }

            $result = $this->service->approveByLinkingUser($telegramUser, $selectedUser, $telegramUserRepository, $telegramBotService);
            if (!$result->success) {
                $this->addFlash('error', $result->errorMessage ?? 'Unable to approve telegram user.');

                return $this->redirectToRoute('admin_telegram_pending');
            }

            $this->addFlash('success', 'Telegram user approved and linked.');

            return $this->redirectToRoute('admin_telegram_pending');
        }

        return $this->render('admin/telegram/approve.html.twig', [
            'telegramUser' => $telegramUser,
            'approveForm' => $form,
        ]);
    }

    #[Route('/{id}/create-user', name: 'admin_telegram_create_user', methods: ['GET', 'POST'])]
    public function createAndApprove(
        TelegramUser $telegramUser,
        Request $request,
        UserRepository $userRepository,
        TelegramUserRepository $telegramUserRepository,
        UserPasswordHasherInterface $passwordHasher,
        TelegramBotService $telegramBotService,
    ): Response {
        $defaultsDto = $this->service->buildCreateFormDefaults($telegramUser);
        $form = $this->createForm(TelegramCreateUserType::class, $defaultsDto->toArray());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $username = mb_strtolower(trim((string) $form->get('username')->getData()));
            $password = (string) $form->get('password')->getData();

            $result = $this->service->createUserAndApprove(
                $telegramUser,
                $username,
                $password,
                $userRepository,
                $telegramUserRepository,
                $passwordHasher,
                $telegramBotService,
            );

            if (!$result->success) {
                $this->addFlash('error', $result->errorMessage ?? 'Unable to create local user.');

                return $this->redirectToRoute('admin_telegram_create_user', ['id' => $telegramUser->getId()]);
            }

            $this->addFlash('success', 'Local user created and Telegram user approved.');

            return $this->redirectToRoute('admin_telegram_pending');
        }

        return $this->render('admin/telegram/create_user.html.twig', [
            'telegramUser' => $telegramUser,
            'createForm' => $form,
        ]);
    }

    #[Route('/{id}/reject', name: 'admin_telegram_reject', methods: ['POST'])]
    public function reject(
        TelegramUser $telegramUser,
        Request $request,
        TelegramUserRepository $telegramUserRepository,
        TelegramBotService $telegramBotService,
    ): Response {
        if (!$this->isCsrfTokenValid('reject_telegram_'.$telegramUser->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $result = $this->service->rejectTelegramUser($telegramUser, $telegramUserRepository, $telegramBotService);
        if (!$result->success) {
            $this->addFlash('error', $result->errorMessage ?? 'Unable to reject telegram user.');

            return $this->redirectToRoute('admin_telegram_pending');
        }

        $this->addFlash('success', 'Telegram registration request rejected.');

        return $this->redirectToRoute('admin_telegram_pending');
    }
}
