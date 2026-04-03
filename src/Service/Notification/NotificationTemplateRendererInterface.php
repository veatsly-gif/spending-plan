<?php

declare(strict_types=1);

namespace App\Service\Notification;

interface NotificationTemplateRendererInterface
{
    public function getTemplateCode(): string;

    /**
     * @param array<string, scalar|null> $triggerPayload
     */
    public function render(array $triggerPayload): NotificationEnvelope;
}
