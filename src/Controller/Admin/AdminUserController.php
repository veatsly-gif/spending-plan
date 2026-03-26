<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\AdminUserCreateType;
use App\Form\Admin\AdminUserEditType;
use App\Form\Admin\AdminUserPasswordType;
use App\Repository\UserRepository;
use App\Service\Controller\Admin\AdminUserControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly AdminUserControllerService $service,
    ) {
    }

    #[Route('', name: 'admin_users_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $dto = $this->service->buildIndexViewData($userRepository);

        return $this->render('admin/users/index.html.twig', $dto->toArray());
    }

    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher): Response
    {
        $draftDto = $this->service->createDraftUser();
        $user = $draftDto->user;

        $form = $this->createForm(AdminUserCreateType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $result = $this->service->createUser($user, $plainPassword, $userRepository, $passwordHasher);
            if (!$result->success) {
                $this->addFlash('error', $result->errorMessage ?? 'Unable to create user.');

                return $this->redirectToRoute('admin_users_index');
            }
            $this->addFlash('success', 'User created.');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, UserRepository $userRepository): Response
    {
        $form = $this->createForm(AdminUserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $this->service->updateUser($user, $userRepository);
            if (!$result->success) {
                $this->addFlash('error', $result->errorMessage ?? 'Unable to update user.');

                return $this->redirectToRoute('admin_users_index');
            }
            $this->addFlash('success', 'User updated.');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/edit.html.twig', [
            'userEntity' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/password', name: 'admin_users_password', methods: ['GET', 'POST'])]
    public function password(User $user, Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(AdminUserPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $result = $this->service->changeUserPassword($user, $plainPassword, $userRepository, $passwordHasher);
            if (!$result->success) {
                $this->addFlash('error', $result->errorMessage ?? 'Unable to change password.');

                return $this->redirectToRoute('admin_users_index');
            }
            $this->addFlash('success', 'Password changed.');

            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/password.html.twig', [
            'userEntity' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, UserRepository $userRepository): Response
    {
        $validationResult = $this->service->validateDelete(
            $this->isCsrfTokenValid('delete_user_'.$user->getId(), (string) $request->request->get('_token')),
            $this->getUser() instanceof User ? $this->getUser() : null,
            $user,
        );

        if (!$validationResult->success && 'Invalid CSRF token.' === $validationResult->errorMessage) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$validationResult->success) {
            $this->addFlash('error', $validationResult->errorMessage ?? 'Unable to remove user.');

            return $this->redirectToRoute('admin_users_index');
        }

        $result = $this->service->removeUser($user, $userRepository);
        if (!$result->success) {
            $this->addFlash('error', $result->errorMessage ?? 'Unable to remove user.');

            return $this->redirectToRoute('admin_users_index');
        }
        $this->addFlash('success', 'User removed.');

        return $this->redirectToRoute('admin_users_index');
    }
}
