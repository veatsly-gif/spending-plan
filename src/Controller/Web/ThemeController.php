<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\UserMetadataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ThemeController extends AbstractController
{
    public function __construct(
        private readonly UserMetadataService $userMetadataService,
    ) {
    }

    #[Route('/theme/{theme}', name: 'app_set_theme', methods: ['GET'])]
    public function setTheme(string $theme, Request $request): JsonResponse
    {
        $theme = mb_strtolower(trim($theme));
        if (!in_array($theme, ['light', 'dark'], true)) {
            return new JsonResponse(['error' => 'Invalid theme. Must be "light" or "dark"'], 400);
        }

        // Set cookie for backward compatibility
        $response = new JsonResponse(['success' => true, 'theme' => $theme]);
        $response->headers->setCookie(
            Cookie::create('_theme')
                ->withValue($theme)
                ->withExpires(strtotime('+1 year'))
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(false)
                ->withSameSite($request->isSecure() ? 'None' : 'Lax')
        );

        // Persist theme to user preferences if user is authenticated
        $user = $this->getUser();
        if (null !== $user) {
            $this->userMetadataService->updatePreference($user, 'theme', $theme);
        }

        return $response;
    }
}
