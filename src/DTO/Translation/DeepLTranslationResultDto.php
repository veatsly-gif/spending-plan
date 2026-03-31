<?php

declare(strict_types=1);

namespace App\DTO\Translation;

final readonly class DeepLTranslationResultDto
{
    public const ERROR_CONFIG = 'config';
    public const ERROR_USAGE_UNAVAILABLE = 'usage_unavailable';
    public const ERROR_LIMIT_CLOSE = 'limit_close';
    public const ERROR_QUOTA_EXCEEDED = 'quota_exceeded';
    public const ERROR_API = 'api_error';

    public function __construct(
        public bool $success,
        public ?string $translatedText,
        public ?DeepLUsageDto $usage,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {
    }
}
