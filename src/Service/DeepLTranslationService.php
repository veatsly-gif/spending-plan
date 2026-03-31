<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Translation\DeepLTranslationResultDto;
use App\DTO\Translation\DeepLUsageDto;
use App\Entity\ApiLimit;
use App\Repository\ApiLimitRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class DeepLTranslationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ApiLimitRepository $apiLimitRepository,
        private readonly LoggerInterface $logger,
        private readonly ?string $deepLApiKey,
        private readonly string $deepLApiBaseUrl,
        private readonly int $deepLUsageStopPercent,
    ) {
    }

    public function translateGeorgianToRussian(string $text): DeepLTranslationResultDto
    {
        $authKey = trim((string) $this->deepLApiKey);
        if ('' === $authKey) {
            return new DeepLTranslationResultDto(
                false,
                null,
                null,
                DeepLTranslationResultDto::ERROR_CONFIG,
                'DeepL API key is empty.'
            );
        }

        $usageFetch = $this->fetchUsage($authKey);
        $usageBefore = $usageFetch['usage'];
        if (!$usageBefore instanceof DeepLUsageDto) {
            if (DeepLTranslationResultDto::ERROR_CONFIG === $usageFetch['errorCode']) {
                return new DeepLTranslationResultDto(
                    false,
                    null,
                    null,
                    DeepLTranslationResultDto::ERROR_CONFIG,
                    $usageFetch['errorMessage'] ?? 'DeepL API key is invalid.'
                );
            }

            return new DeepLTranslationResultDto(
                false,
                null,
                null,
                DeepLTranslationResultDto::ERROR_USAGE_UNAVAILABLE,
                'Unable to read DeepL usage.'
            );
        }

        $requestedCharacters = mb_strlen($text);
        if ($this->isCloseToLimit($usageBefore, $requestedCharacters)) {
            return new DeepLTranslationResultDto(
                false,
                null,
                $usageBefore,
                DeepLTranslationResultDto::ERROR_LIMIT_CLOSE,
                'DeepL limit is almost reached.'
            );
        }

        try {
            $response = $this->httpClient->request(
                'POST',
                $this->buildUrl('/v2/translate'),
                [
                    'headers' => [
                        'Authorization' => 'DeepL-Auth-Key '.$authKey,
                    ],
                    'json' => [
                        'text' => [$text],
                        'source_lang' => 'KA',
                        'target_lang' => 'RU',
                    ],
                ],
            );
        } catch (\Throwable $exception) {
            $this->logger->error('DeepL translation request failed.', [
                'error' => $exception->getMessage(),
            ]);

            return new DeepLTranslationResultDto(
                false,
                null,
                $usageBefore,
                DeepLTranslationResultDto::ERROR_API,
                'DeepL request failed.'
            );
        }

        $statusCode = $response->getStatusCode();
        $payload = $response->toArray(false);
        if (456 === $statusCode) {
            $usageAfterLimitFetch = $this->fetchUsage($authKey);
            $usageAfterLimit = $usageAfterLimitFetch['usage'] ?? $usageBefore;

            return new DeepLTranslationResultDto(
                false,
                null,
                $usageAfterLimit,
                DeepLTranslationResultDto::ERROR_QUOTA_EXCEEDED,
                'DeepL quota exceeded.'
            );
        }

        $translatedText = $payload['translations'][0]['text'] ?? null;
        if (!is_string($translatedText) || '' === trim($translatedText)) {
            $description = is_string($payload['message'] ?? null)
                ? $payload['message']
                : sprintf('Unexpected DeepL response (HTTP %d).', $statusCode);
            $this->logger->error('DeepL translation failed.', [
                'status_code' => $statusCode,
                'payload' => $payload,
            ]);

            return new DeepLTranslationResultDto(
                false,
                null,
                $usageBefore,
                DeepLTranslationResultDto::ERROR_API,
                $description
            );
        }

        $usageAfterFetch = $this->fetchUsage($authKey);
        $usageAfter = $usageAfterFetch['usage'] ?? $usageBefore;

        return new DeepLTranslationResultDto(
            true,
            $translatedText,
            $usageAfter,
        );
    }

    /**
     * @return array{
     *     usage: DeepLUsageDto|null,
     *     errorCode: string|null,
     *     errorMessage: string|null
     * }
     */
    private function fetchUsage(string $authKey): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $this->buildUrl('/v2/usage'),
                [
                    'headers' => [
                        'Authorization' => 'DeepL-Auth-Key '.$authKey,
                    ],
                ],
            );
            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (\Throwable $exception) {
            $this->logger->error('DeepL usage request failed.', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'usage' => null,
                'errorCode' => DeepLTranslationResultDto::ERROR_USAGE_UNAVAILABLE,
                'errorMessage' => 'Unable to read DeepL usage.',
            ];
        }

        $characterCount = (int) ($payload['character_count'] ?? -1);
        $characterLimit = (int) ($payload['character_limit'] ?? -1);
        if ($statusCode >= 400 || $characterCount < 0 || $characterLimit < 0) {
            $this->logger->error('DeepL usage response is invalid.', [
                'status_code' => $statusCode,
                'payload' => $payload,
            ]);

            if (403 === $statusCode) {
                return [
                    'usage' => null,
                    'errorCode' => DeepLTranslationResultDto::ERROR_CONFIG,
                    'errorMessage' => 'DeepL API key is invalid or disabled.',
                ];
            }

            return [
                'usage' => null,
                'errorCode' => DeepLTranslationResultDto::ERROR_USAGE_UNAVAILABLE,
                'errorMessage' => 'Unable to read DeepL usage.',
            ];
        }

        $usage = new DeepLUsageDto($characterCount, $characterLimit);

        $apiLimit = (new ApiLimit())
            ->setProvider(ApiLimit::PROVIDER_DEEPL)
            ->setCharacterCount($usage->characterCount)
            ->setCharacterLimit($usage->characterLimit)
            ->setRemainingCharacters($usage->remainingCharacters())
            ->setUsagePercent($usage->usagePercent())
            ->setCloseToLimit($this->isCloseToLimit($usage, 0));

        $this->apiLimitRepository->save($apiLimit, true);

        return [
            'usage' => $usage,
            'errorCode' => null,
            'errorMessage' => null,
        ];
    }

    private function isCloseToLimit(DeepLUsageDto $usage, int $requestedCharacters): bool
    {
        if ($usage->characterLimit <= 0) {
            return false;
        }

        if ($usage->remainingCharacters() < $requestedCharacters) {
            return true;
        }

        return $usage->usagePercent() >= max(1, min(100, $this->deepLUsageStopPercent));
    }

    private function buildUrl(string $path): string
    {
        return rtrim(trim($this->deepLApiBaseUrl), '/').$path;
    }
}
