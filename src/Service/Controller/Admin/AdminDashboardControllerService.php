<?php

declare(strict_types=1);

namespace App\Service\Controller\Admin;

use App\DTO\Controller\Admin\AdminDashboardViewDto;
use App\Repository\TelegramUserRepository;

final class AdminDashboardControllerService
{
    public function buildViewData(TelegramUserRepository $telegramUserRepository): AdminDashboardViewDto
    {
        return new AdminDashboardViewDto($telegramUserRepository->countPending());
    }
}
