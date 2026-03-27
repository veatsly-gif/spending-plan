<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\AdminPopupNotificationStore;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminPopupExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly AdminPopupNotificationStore $popupNotificationStore,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_popup', [$this, 'consumeAdminPopup']),
        ];
    }

    /**
     * @return array{
     *     show: bool,
     *     title: string,
     *     message: string,
     *     monthKey: string
     * }
     */
    public function consumeAdminPopup(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return [
                'show' => false,
                'title' => '',
                'message' => '',
                'monthKey' => '',
            ];
        }

        return $this->popupNotificationStore
            ->consumeDailyPopup((int) $user->getId(), new \DateTimeImmutable())
            ->toArray();
    }
}
