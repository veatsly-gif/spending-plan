<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\Frontend\FrontendModeResolver;
use App\Service\Controller\Web\SecurityControllerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly SecurityControllerService $service,
        private readonly FrontendModeResolver $frontendModeResolver,
    ) {
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $isReactMode = $this->frontendModeResolver->isReactMode();

        if ($isReactMode) {
            return $this->redirectToRoute('web_spa_entry', [
                'path' => null !== $this->getUser() ? 'dashboard' : 'login',
            ]);
        }

        $dto = $this->service->buildLoginViewData(
            $authenticationUtils,
            null !== $this->getUser()
        );

        if ($dto->shouldRedirect && null !== $dto->redirectRoute) {
            return $this->redirectToRoute($dto->redirectRoute);
        }

        return $this->render('security/login.html.twig', $dto->context);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        $decision = $this->service->buildLogoutDecision();
        if ($decision->delegateToFirewall) {
            throw new \LogicException('Handled by the firewall.');
        }

        throw new \LogicException('Logout handling is not configured.');
    }
}
