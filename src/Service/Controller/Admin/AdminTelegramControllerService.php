<?php

declare(strict_types=1);

namespace App\Service\Controller\Admin;

use App\DTO\Controller\Admin\AdminActionResultDto;
use App\DTO\Controller\Admin\AdminDecisionDto;
use App\DTO\Controller\Admin\AdminTelegramCreateDefaultsDto;
use App\DTO\Controller\Admin\AdminTelegramPendingViewDto;
use App\Entity\TelegramUser;
use App\Entity\User;
use App\Repository\TelegramUserRepository;
use App\Repository\UserRepository;
use App\Service\TelegramBotService;
use App\Service\TelegramMiniAppTokenService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AdminTelegramControllerService
{
    public function __construct(
        private readonly TelegramMiniAppTokenService $miniAppTokenService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildPendingViewData(TelegramUserRepository $telegramUserRepository): AdminTelegramPendingViewDto
    {
        /** @var array<int, TelegramUser> $telegramUsers */
        $telegramUsers = $telegramUserRepository->findPending();

        return new AdminTelegramPendingViewDto($telegramUsers);
    }

    public function shouldRedirectApproved(TelegramUser $telegramUser): AdminDecisionDto
    {
        return new AdminDecisionDto(TelegramUser::STATUS_AUTHORIZED === $telegramUser->getStatus());
    }

    public function approveByLinkingUser(
        TelegramUser $telegramUser,
        User $selectedUser,
        TelegramUserRepository $telegramUserRepository,
        TelegramBotService $telegramBotService,
    ): AdminActionResultDto {
        $telegramUser
            ->setUser($selectedUser)
            ->setStatus(TelegramUser::STATUS_AUTHORIZED)
            ->setAuthorizedAt(new \DateTimeImmutable());
        $telegramUserRepository->save($telegramUser, true);

        $telegramBotService->sendMessage(
            $telegramUser->getTelegramId(),
            sprintf('Approved. You are linked to user %s.', $selectedUser->getUsername())
        );
        $telegramBotService->sendMessageWithWebAppButton(
            $telegramUser->getTelegramId(),
            'Open mini-app to add spend.',
            'Add spend',
            $this->buildMiniAppUrl($telegramUser->getTelegramId())
        );

        return new AdminActionResultDto(true);
    }

    public function buildCreateFormDefaults(TelegramUser $telegramUser): AdminTelegramCreateDefaultsDto
    {
        return new AdminTelegramCreateDefaultsDto(sprintf('tg_%s', $telegramUser->getTelegramId()));
    }

    public function createUserAndApprove(
        TelegramUser $telegramUser,
        string $username,
        string $password,
        UserRepository $userRepository,
        TelegramUserRepository $telegramUserRepository,
        UserPasswordHasherInterface $passwordHasher,
        TelegramBotService $telegramBotService,
    ): AdminActionResultDto {
        if (null !== $userRepository->findOneBy(['username' => $username])) {
            return new AdminActionResultDto(false, 'Username already exists.');
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
        $telegramBotService->sendMessageWithWebAppButton(
            $telegramUser->getTelegramId(),
            'Open mini-app to add spend.',
            'Add spend',
            $this->buildMiniAppUrl($telegramUser->getTelegramId())
        );

        return new AdminActionResultDto(true);
    }

    public function rejectTelegramUser(
        TelegramUser $telegramUser,
        TelegramUserRepository $telegramUserRepository,
        TelegramBotService $telegramBotService,
    ): AdminActionResultDto {
        if (TelegramUser::STATUS_AUTHORIZED === $telegramUser->getStatus()) {
            return new AdminActionResultDto(false, 'Authorized user cannot be rejected from this list.');
        }

        $telegramUser
            ->setUser(null)
            ->setStatus(TelegramUser::STATUS_REJECTED)
            ->setAuthorizedAt(null);
        $telegramUserRepository->save($telegramUser, true);

        $telegramBotService->sendMessage(
            $telegramUser->getTelegramId(),
            'Your registration request was rejected by admin. You can try again with /reg.'
        );

        return new AdminActionResultDto(true);
    }

    private function buildMiniAppUrl(string $telegramId): string
    {
        $token = $this->miniAppTokenService->generateToken($telegramId);

        $absoluteUrl = $this->urlGenerator->generate(
            'app_telegram_mini_spend',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        if (!$this->needsPublicHostOverride($absoluteUrl)) {
            return $absoluteUrl;
        }

        $publicHost = $this->resolveTelegramPublicHost();
        if ('' === $publicHost) {
            return $absoluteUrl;
        }

        $path = (string) parse_url($absoluteUrl, PHP_URL_PATH);
        $query = (string) parse_url($absoluteUrl, PHP_URL_QUERY);

        return $publicHost.$path.('' !== $query ? '?'.$query : '');
    }

    private function resolveTelegramPublicHost(): string
    {
        $raw = (string) (
            $_ENV['TELEGRAM_PUBLIC_HOST']
            ?? $_SERVER['TELEGRAM_PUBLIC_HOST']
            ?? getenv('TELEGRAM_PUBLIC_HOST')
            ?: ''
        );
        $host = rtrim(trim($raw), '/');
        if ('' === $host) {
            return '';
        }

        if (!str_starts_with($host, 'http://') && !str_starts_with($host, 'https://')) {
            $host = 'https://'.$host;
        }

        if (str_starts_with($host, 'http://')) {
            $host = 'https://'.substr($host, 7);
        }

        return $host;
    }

    private function needsPublicHostOverride(string $absoluteUrl): bool
    {
        $scheme = strtolower((string) parse_url($absoluteUrl, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($absoluteUrl, PHP_URL_HOST));
        if ('https' !== $scheme) {
            return true;
        }

        return '' === $host
            || 'localhost' === $host
            || '127.0.0.1' === $host
            || '0.0.0.0' === $host
            || str_ends_with($host, '.localhost');
    }
}
