<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

use App\Entity\SpendingPlan;

final readonly class AdminSpendingPlanDraftDto
{
    public function __construct(
        public SpendingPlan $spendingPlan,
    ) {
    }
}
