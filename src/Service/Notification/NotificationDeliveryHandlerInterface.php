<?php

declare(strict_types=1);

namespace App\Service\Notification;

interface NotificationDeliveryHandlerInterface
{
    public function getDeliveryType(): string;

    public function deliver(NotificationDelivery $notification): void;
}
