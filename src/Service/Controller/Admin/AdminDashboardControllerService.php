<?php

declare(strict_types=1);

namespace App\Service\Controller\Admin;

use App\DTO\Controller\Admin\AdminDashboardViewDto;
use App\DTO\Controller\Admin\AdminSpendingPlanPopupDto;
use App\Repository\SpendingPlanRepository;
use App\Repository\TelegramUserRepository;
use App\Util\RussianCalendarFormatter;

final class AdminDashboardControllerService
{
    public function buildViewData(
        TelegramUserRepository $telegramUserRepository,
        SpendingPlanRepository $spendingPlanRepository,
    ): AdminDashboardViewDto
    {
        $now = new \DateTimeImmutable();
        $nextMonthStart = $now->modify('first day of next month')->setTime(0, 0);
        $nextMonthEnd = $nextMonthStart->modify('last day of this month')->setTime(0, 0);

        return new AdminDashboardViewDto(
            $telegramUserRepository->countPending(),
            RussianCalendarFormatter::monthYear($nextMonthStart),
            $spendingPlanRepository->countSystemPlansForPeriod($nextMonthStart, $nextMonthEnd),
            new AdminSpendingPlanPopupDto(false, '', '', '')
        );
    }
}
