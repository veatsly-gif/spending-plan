<?php

declare(strict_types=1);

namespace App\Service\TelegramMiniApp;

use App\Entity\TelegramUser;
use App\Entity\User;
use App\Repository\TelegramUserRepository;
use App\Service\TelegramMiniAppTokenService;
use Symfony\Component\HttpFoundation\Request;

final readonly class TelegramMiniAppUserResolver
{
    public function __construct(
        private TelegramMiniAppTokenService $miniAppTokenService,
        private TelegramUserRepository $telegramUserRepository,
    ) {
    }

    public function resolveUser(Request $request): ?User
    {
        $token = trim((string) $request->query->get('token', ''));
        if ('' === $token) {
            return null;
        }

        $telegramId = $this->miniAppTokenService->resolveTelegramId($token);
        if (null === $telegramId) {
            return null;
        }

        $telegramUser = $this->telegramUserRepository->findOneBy(['telegramId' => $telegramId]);
        if (!$telegramUser instanceof TelegramUser) {
            return null;
        }

        if (TelegramUser::STATUS_AUTHORIZED !== $telegramUser->getStatus()) {
            return null;
        }

        return $telegramUser->getUser();
    }
}
