<?php

declare(strict_types=1);

namespace App\Service\Notification\Template;

use App\Service\Notification\NotificationEnvelope;
use App\Service\Notification\NotificationTemplateRendererInterface;

final class SpendingPlanMissingNextMonthTemplateRenderer implements NotificationTemplateRendererInterface
{
    public function getTemplateCode(): string
    {
        return 'spending_plan_missing_next_month';
    }

    /**
     * @param array<string, scalar|null> $triggerPayload
     */
    public function render(array $triggerPayload): NotificationEnvelope
    {
        $monthLabel = (string) ($triggerPayload['monthLabel'] ?? 'next month');

        return new NotificationEnvelope([
            'popup' => [
                'title' => 'Spending Plan Reminder',
                'message' => sprintf('Please prepare %s spending plans.', $monthLabel),
                'monthKey' => (string) ($triggerPayload['monthKey'] ?? ''),
            ],
            'telegram' => sprintf('Reminder: please prepare %s spending plans.', $monthLabel),
        ]);
    }
}
