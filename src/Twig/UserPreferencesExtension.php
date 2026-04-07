<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\UserMetadataService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class UserPreferencesExtension extends AbstractExtension
{
    public function __construct(
        private readonly UserMetadataService $userMetadataService,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('user_preferences', [$this, 'getUserPreferences']),
        ];
    }

    /**
     * @return array{language: string, theme: string}
     */
    public function getUserPreferences(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [
                'language' => 'en',
                'theme' => 'light',
            ];
        }

        return $this->userMetadataService->getPreferences($user);
    }
}
