<?php

declare(strict_types=1);

namespace App\Service\Notification\Template;

use App\Service\Notification\NotificationActionService;
use App\Service\Notification\NotificationEnvelope;
use App\Service\Notification\NotificationTemplateRendererInterface;

final class DeclarationSendTaxServiceTemplateRenderer implements NotificationTemplateRendererInterface
{
    public function getTemplateCode(): string
    {
        return NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE;
    }

    /**
     * @param array<string, scalar|null> $triggerPayload
     */
    public function render(array $triggerPayload): NotificationEnvelope
    {
        $monthKey = (string) ($triggerPayload['monthKey'] ?? '');
        $message = "It's day to send a declaration to georgian tax service";
        $actions = [
            [
                'code' => NotificationActionService::ACTION_DONE,
                'label' => 'Already done',
            ],
            [
                'code' => NotificationActionService::ACTION_REMIND_LATER,
                'label' => 'Remind me later',
            ],
        ];

        return new NotificationEnvelope([
            'popup' => [
                'title' => 'Tax Declaration Reminder',
                'message' => $message,
                'monthKey' => $monthKey,
                'template' => $this->getTemplateCode(),
                'actions' => $actions,
            ],
            'telegram' => [
                'text' => $message,
                'monthKey' => $monthKey,
                'actions' => $actions,
            ],
        ]);
    }
}
