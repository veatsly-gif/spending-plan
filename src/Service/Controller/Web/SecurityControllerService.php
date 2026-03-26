<?php

declare(strict_types=1);

namespace App\Service\Controller\Web;

use App\DTO\Controller\Web\LoginViewDto;
use App\DTO\Controller\Web\LogoutDecisionDto;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityControllerService
{
    public function buildLoginViewData(AuthenticationUtils $authenticationUtils, bool $isAuthenticated): LoginViewDto
    {
        if ($isAuthenticated) {
            return new LoginViewDto(true, 'app_dashboard');
        }

        return new LoginViewDto(false, null, [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    public function buildLogoutDecision(): LogoutDecisionDto
    {
        return new LogoutDecisionDto(true);
    }
}
