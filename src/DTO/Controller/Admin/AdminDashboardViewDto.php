<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

final readonly class AdminDashboardViewDto
{
    public function __construct(
        public int $pendingTelegramUsers,
        public string $nextMonthLabel,
        public int $nextMonthSystemSpendingPlansCount,
        public AdminSpendingPlanPopupDto $popup,
    ) {
    }

    /**
     * @return array{
     *     pendingTelegramUsers: int,
     *     nextMonthLabel: string,
     *     nextMonthSystemSpendingPlansCount: int,
     *     popup: array{
     *         show: bool,
     *         title: string,
     *         message: string,
     *         monthKey: string
     *     }
     * }
     */
    public function toArray(): array
    {
        return [
            'pendingTelegramUsers' => $this->pendingTelegramUsers,
            'nextMonthLabel' => $this->nextMonthLabel,
            'nextMonthSystemSpendingPlansCount' => $this->nextMonthSystemSpendingPlansCount,
            'popup' => $this->popup->toArray(),
        ];
    }
}
