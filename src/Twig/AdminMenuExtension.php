<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\TelegramUser;
use App\Repository\TelegramUserRepository;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminMenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly TelegramUserRepository $telegramUserRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_menu_data', [$this, 'getAdminMenuData'], ['needs_environment' => true]),
        ];
    }

    public function getAdminMenuData(Environment $env): array
    {
        $pendingCount = $this->telegramUserRepository->count(['status' => TelegramUser::STATUS_PENDING]);

        return [
            'pending_telegram_count' => $pendingCount,
        ];
    }
}
