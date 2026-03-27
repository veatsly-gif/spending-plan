<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
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

        $redirect = $request->query->get('redirect');
        if (is_string($redirect) && '' !== $redirect && str_starts_with($redirect, '/')) {
            return $this->redirect($redirect);
        }

        $referer = $request->headers->get('referer');
        if (is_string($referer) && '' !== $referer) {
            $host = (string) $request->getSchemeAndHttpHost();
            if (str_starts_with($referer, $host)) {
                $path = mb_substr($referer, mb_strlen($host));
                if (str_starts_with($path, '/')) {
                    return $this->redirect($path);
                }
            }
        }

        return $this->redirectToRoute('web_home');
    }
}
