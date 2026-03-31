<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\Translation\DeepLTranslationResultDto;
use App\Entity\ApiLimit;
use App\Repository\ApiLimitRepository;
use App\Service\DeepLTranslationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DeepLTranslationServiceTest extends TestCase
{
    public function testTranslateGeorgianToRussianReturnsTranslatedText(): void
    {
        $responses = [
            new MockResponse(json_encode(['character_count' => 100, 'character_limit' => 500000], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['translations' => [['text' => 'привет']]], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['character_count' => 108, 'character_limit' => 500000], JSON_THROW_ON_ERROR)),
        ];

        $httpClient = new MockHttpClient(static function () use (&$responses): MockResponse {
            $response = array_shift($responses);
            if (!$response instanceof MockResponse) {
                self::fail('Unexpected extra HTTP request.');
            }

            return $response;
        });

        $apiLimitRepository = $this->createApiLimitRepositoryMock();
        $apiLimitRepository->expects($this->exactly(2))
            ->method('save')
            ->with(
                $this->callback(static fn (mixed $value): bool => $value instanceof ApiLimit
                    && ApiLimit::PROVIDER_DEEPL === $value->getProvider()
                    && $value->getCharacterLimit() === 500000),
                true
            );

        $service = new DeepLTranslationService(
            $httpClient,
            $apiLimitRepository,
            new NullLogger(),
            'deepl-key',
            'https://api-free.deepl.com',
            95,
        );

        $result = $service->translateGeorgianToRussian('გამარჯობა');

        self::assertTrue($result->success);
        self::assertSame('привет', $result->translatedText);
        self::assertNull($result->errorCode);
    }

    public function testTranslateStopsWhenUsageIsCloseToLimit(): void
    {
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse(
            json_encode(['character_count' => 490000, 'character_limit' => 500000], JSON_THROW_ON_ERROR)
        ));

        $apiLimitRepository = $this->createApiLimitRepositoryMock();
        $apiLimitRepository->expects($this->once())->method('save');

        $service = new DeepLTranslationService(
            $httpClient,
            $apiLimitRepository,
            new NullLogger(),
            'deepl-key',
            'https://api-free.deepl.com',
            95,
        );

        $result = $service->translateGeorgianToRussian('გამარჯობა');

        self::assertFalse($result->success);
        self::assertSame(DeepLTranslationResultDto::ERROR_LIMIT_CLOSE, $result->errorCode);
    }

    /**
     * @return ApiLimitRepository&MockObject
     */
    private function createApiLimitRepositoryMock(): ApiLimitRepository
    {
        return $this->getMockBuilder(ApiLimitRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['save'])
            ->getMock();
    }
}
