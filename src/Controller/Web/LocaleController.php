<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\UserMetadataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    public function __construct(
        private readonly UserMetadataService $userMetadataService,
    ) {
    }

    #[Route('/locale/{locale}', name: 'app_set_locale', methods: ['GET'])]
    public function __invoke(string $locale, Request $request): RedirectResponse
    {
        $locale = mb_strtolower(trim($locale));
        if (!in_array($locale, ['en', 'ru'], true)) {
            $locale = 'en';
        }

        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $locale);
        }

        $request->setLocale($locale);

        // Persist locale to user preferences if user is authenticated
        $user = $this->getUser();
        if (null !== $user) {
            $this->userMetadataService->updatePreference($user, 'language', $locale);
        }

        $response = null;
        $redirect = $request->query->get('redirect');
        if (is_string($redirect) && '' !== $redirect && str_starts_with($redirect, '/')) {
            // Add _locale as a query parameter to ensure it's applied on the redirected page
            // This is crucial for Telegram WebView where cookies might not persist reliably
            $separator = str_contains($redirect, '?') ? '&' : '?';
            $redirectWithLocale = $redirect . $separator . '_locale=' . urlencode($locale);
            $response = $this->redirect($redirectWithLocale);
        }

        if (!$response instanceof RedirectResponse) {
            $referer = $request->headers->get('referer');
            if (is_string($referer) && '' !== $referer) {
                $host = (string) $request->getSchemeAndHttpHost();
                if (str_starts_with($referer, $host)) {
                    $path = mb_substr($referer, mb_strlen($host));
                    if (str_starts_with($path, '/')) {
                        // Add _locale as a query parameter to ensure it's applied
                        $separator = str_contains($path, '?') ? '&' : '?';
                        $pathWithLocale = $path . $separator . '_locale=' . urlencode($locale);
                        $response = $this->redirect($pathWithLocale);
                    }
                }
            }
        }

        if (!$response instanceof RedirectResponse) {
            $response = $this->redirectToRoute('web_home');
        }

        $response->headers->setCookie(
            Cookie::create('_locale')
                ->withValue($locale)
                ->withExpires(strtotime('+1 year'))
                ->withPath('/')
                ->withSecure($request->isSecure())
                ->withHttpOnly(false)
                // Use SameSite=None for better compatibility with Telegram WebView
                // Cookies with SameSite=None must also be Secure
                ->withSameSite($request->isSecure() ? 'None' : 'Lax')
        );

        return $response;
    }
}
