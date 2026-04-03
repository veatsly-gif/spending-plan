<?php

declare(strict_types=1);

namespace App\Triggers;

use App\Entity\User;
use App\Service\Notification\NotificationActionService;

final class DeclarationSendTrigger implements NotificationTriggerInterface
{
    public function __construct(
        private readonly NotificationActionService $notificationActionService,
    ) {
    }

    public function getCode(): string
    {
        return 'declaration-send';
    }

    /**
     * @return array{
     *     monthKey: string
     * }|null
     */
    public function evaluate(User $admin, \DateTimeImmutable $now): ?array
    {
        if (!in_array('ROLE_ADMIN', $admin->getRoles(), true)) {
            return null;
        }

        $monthKey = $now->format('Y-m');
        if (!$this->notificationActionService->shouldNotify(
            (int) $admin->getId(),
            NotificationActionService::TEMPLATE_DECLARATION_SEND_TAX_SERVICE,
            $monthKey,
            $now
        )) {
            return null;
        }

        return [
            'monthKey' => $monthKey,
        ];
    }
}
