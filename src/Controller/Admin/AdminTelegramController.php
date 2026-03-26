<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TelegramUser;
use App\Entity\User;
use App\Form\Admin\TelegramApproveType;
use App\Form\Admin\TelegramCreateUserType;
use App\Repository\TelegramUserRepository;
use App\Repository\UserRepository;
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
    #[Route('', name: 'admin_telegram_pending', methods: ['GET'])]
    public function index(TelegramUserRepository $telegramUserRepository): Response
    {
        return $this->render('admin/telegram/pending.html.twig', [
            'telegramUsers' => $telegramUserRepository->findPending(),
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_telegram_approve', methods: ['GET', 'POST'])]
    public function approve(
        TelegramUser $telegramUser,
        Request $request,
        TelegramUserRepository $telegramUserRepository,
        TelegramBotService $telegramBotService,
    ): Response {
        if (TelegramUser::STATUS_AUTHORIZED === $telegramUser->getStatus()) {
            return $this->redirectToRoute('admin_telegram_pending');
        }

        $form = $this->createForm(TelegramApproveType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedUser = $form->get('user')->getData();
            if (!$selectedUser instanceof User) {
                throw $this->createNotFoundException('User is required.');
            }

            $telegramUser
                ->setUser($selectedUser)
                ->setStatus(TelegramUser::STATUS_AUTHORIZED)
                ->setAuthorizedAt(new \DateTimeImmutable());
            $telegramUserRepository->save($telegramUser, true);

            $telegramBotService->sendMessage(
                $telegramUser->getTelegramId(),
                sprintf('Approved. You are linked to user %s. Bot stub is available.', $selectedUser->getUsername())
            );

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
        $form = $this->createForm(TelegramCreateUserType::class, [
            'username' => sprintf('tg_%s', $telegramUser->getTelegramId()),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $username = mb_strtolower(trim((string) $form->get('username')->getData()));
            $password = (string) $form->get('password')->getData();

            if (null !== $userRepository->findOneBy(['username' => $username])) {
                $this->addFlash('error', 'Username already exists.');

                return $this->redirectToRoute('admin_telegram_create_user', ['id' => $telegramUser->getId()]);
            }

            $user = (new User())
                ->setUsername($username)
                ->setRoles(['ROLE_USER'])
                ->setPassword('temp');
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            $userRepository->save($user, true);

            $telegramUser
                ->setUser($user)
                ->setStatus(TelegramUser::STATUS_AUTHORIZED)
                ->setAuthorizedAt(new \DateTimeImmutable());
            $telegramUserRepository->save($telegramUser, true);

            $telegramBotService->sendMessage(
                $telegramUser->getTelegramId(),
                sprintf('Approved. A local account "%s" was created for you.', $user->getUsername())
            );

            $this->addFlash('success', 'Local user created and Telegram user approved.');

            return $this->redirectToRoute('admin_telegram_pending');
        }

        return $this->render('admin/telegram/create_user.html.twig', [
            'telegramUser' => $telegramUser,
            'createForm' => $form,
        ]);
    }
}
