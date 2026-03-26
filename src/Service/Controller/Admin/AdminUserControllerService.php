<?php

declare(strict_types=1);

namespace App\Service\Controller\Admin;

use App\DTO\Controller\Admin\AdminActionResultDto;
use App\DTO\Controller\Admin\AdminUserDraftDto;
use App\DTO\Controller\Admin\AdminUsersIndexViewDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminUserControllerService
{
    public function buildIndexViewData(UserRepository $userRepository): AdminUsersIndexViewDto
    {
        /** @var array<int, User> $users */
        $users = $userRepository->findBy([], ['id' => 'ASC']);

        return new AdminUsersIndexViewDto($users);
    }

    public function createDraftUser(): AdminUserDraftDto
    {
        $user = new User();
        $user->setRoles(['ROLE_USER']);

        return new AdminUserDraftDto($user);
    }

    public function createUser(
        User $user,
        string $plainPassword,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
    ): AdminActionResultDto {
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $userRepository->save($user, true);

        return new AdminActionResultDto(true);
    }

    public function updateUser(User $user, UserRepository $userRepository): AdminActionResultDto
    {
        $userRepository->save($user, true);

        return new AdminActionResultDto(true);
    }

    public function changeUserPassword(
        User $user,
        string $plainPassword,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
    ): AdminActionResultDto {
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $userRepository->save($user, true);

        return new AdminActionResultDto(true);
    }

    public function validateDelete(bool $isCsrfValid, ?User $currentUser, User $targetUser): AdminActionResultDto
    {
        if (!$isCsrfValid) {
            return new AdminActionResultDto(false, 'Invalid CSRF token.');
        }

        if (null !== $currentUser && $currentUser->getId() === $targetUser->getId()) {
            return new AdminActionResultDto(false, 'You cannot delete your own account.');
        }

        return new AdminActionResultDto(true);
    }

    public function removeUser(User $user, UserRepository $userRepository): AdminActionResultDto
    {
        $userRepository->remove($user, true);

        return new AdminActionResultDto(true);
    }
}
