<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    private const DEFAULT_LOCALE = 'en';

    /**
     * @var list<string>
     */
    private const SUPPORTED_LOCALES = ['en', 'ru'];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->hasSession() ? $request->getSession() : null;

        $queryLocale = $request->query->get('_locale');
        $queryLocale = is_string($queryLocale) ? $this->normalizeLocale($queryLocale) : null;
        if (null !== $queryLocale) {
            $request->setLocale($queryLocale);
            $request->attributes->set('_locale', $queryLocale);
            $session?->set('_locale', $queryLocale);

            return;
        }

        $sessionLocale = $session?->get('_locale');
        $sessionLocale = is_string($sessionLocale) ? $this->normalizeLocale($sessionLocale) : null;
        if (null !== $sessionLocale) {
            $request->setLocale($sessionLocale);
            $request->attributes->set('_locale', $sessionLocale);

            return;
        }

        $cookieLocale = $request->cookies->get('_locale');
        $cookieLocale = is_string($cookieLocale) ? $this->normalizeLocale($cookieLocale) : null;
        if (null !== $cookieLocale) {
            $request->setLocale($cookieLocale);
            $request->attributes->set('_locale', $cookieLocale);
            $session?->set('_locale', $cookieLocale);

            return;
        }

        foreach ($request->getLanguages() as $language) {
            $resolved = $this->normalizeLocale($language);
            if (null === $resolved) {
                continue;
            }

            $request->setLocale($resolved);
            $request->attributes->set('_locale', $resolved);
            $session?->set('_locale', $resolved);

            return;
        }

        $request->setLocale(self::DEFAULT_LOCALE);
        $request->attributes->set('_locale', self::DEFAULT_LOCALE);
    }

    private function normalizeLocale(string $raw): ?string
    {
        $trimmed = trim(mb_strtolower($raw));
        if ('' === $trimmed) {
            return null;
        }

        $base = explode('-', str_replace('_', '-', $trimmed))[0] ?? '';
        if (!in_array($base, self::SUPPORTED_LOCALES, true)) {
            return null;
        }

        return $base;
    }
}
