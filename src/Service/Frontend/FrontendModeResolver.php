<?php

declare(strict_types=1);

namespace App\Service\Frontend;

final readonly class FrontendModeResolver
{
    public const string MODE_TWIG = 'twig';
    public const string MODE_REACT = 'react';

    public function __construct(
        private string $frontendMode,
        private string $reactDevServerUrl,
        private string $apiBaseUrl,
        private string $appEnv,
    ) {
    }

    public function getMode(): string
    {
        $mode = mb_strtolower(trim($this->frontendMode));

        if (self::MODE_REACT === $mode) {
            return self::MODE_REACT;
        }

        return self::MODE_TWIG;
    }

    public function isReactMode(): bool
    {
        return self::MODE_REACT === $this->getMode();
    }

    public function shouldUseReactDevServer(): bool
    {
        return self::MODE_REACT === $this->getMode()
            && 'prod' !== $this->appEnv
            && '' !== $this->getReactDevServerUrl();
    }

    public function getReactDevServerUrl(): string
    {
        return rtrim(trim($this->reactDevServerUrl), '/');
    }

    public function getApiBaseUrl(): string
    {
        $apiBaseUrl = trim($this->apiBaseUrl);

        if ('' === $apiBaseUrl) {
            return '/api';
        }

        return rtrim($apiBaseUrl, '/');
    }
}
