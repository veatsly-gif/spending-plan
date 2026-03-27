<?php

declare(strict_types=1);

namespace App\DTO\Controller\Admin;

use App\Entity\SpendingPlan;

final readonly class AdminSpendingPlanApproveResultDto
{
    public function __construct(
        public bool $success,
        public ?SpendingPlan $spendingPlan = null,
        public ?string $errorMessage = null,
    ) {
    }
}
