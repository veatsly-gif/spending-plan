<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TelegramBotService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TelegramBotServiceTest extends TestCase
{
    public function testSendMessageSkipsWhenTokenIsEmpty(): void
    {
        $httpClient = new MockHttpClient(static function (): void {
            self::fail('HTTP client must not be called when bot token is empty.');
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('Telegram send skipped: bot token is empty.');

        $service = new TelegramBotService($httpClient, $logger, '   ');
        $service->sendMessage('48995172', 'hello');
    }

    public function testSendMessageThrowsWhenTelegramApiReturnsNotOk(): void
    {
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse(
            json_encode(['ok' => false, 'description' => 'failed'], JSON_THROW_ON_ERROR),
            ['http_code' => 200],
        ));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Telegram send failed.',
                $this->callback(static fn (array $context): bool => '48995172' === ($context['chat_id'] ?? null))
            );

        $service = new TelegramBotService($httpClient, $logger, 'test-token');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Telegram API sendMessage failed.');

        $service->sendMessage('48995172', 'hello');
    }

    public function testSendMessageSendsPayloadAndLogsSuccess(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $capturedPayload = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedPayload): MockResponse {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedPayload = self::extractPayload($options);

            return new MockResponse(
                json_encode(['ok' => true, 'result' => ['message_id' => 1]], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Telegram send attempted.',
                $this->callback(static fn (array $context): bool => 200 === ($context['status_code'] ?? null)
                    && '48995172' === ($context['chat_id'] ?? null)
                    && 'hello' === ($context['text'] ?? null))
            );

        $service = new TelegramBotService($httpClient, $logger, 'token-123');
        $service->sendMessage('48995172', 'hello');

        self::assertSame('POST', $capturedMethod);
        self::assertSame('https://api.telegram.org/bottoken-123/sendMessage', $capturedUrl);
        self::assertSame(
            ['chat_id' => '48995172', 'text' => 'hello'],
            $capturedPayload
        );
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, string>
     */
    private static function extractPayload(array $options): array
    {
        if (isset($options['json']) && is_array($options['json'])) {
            /** @var array<string, string> $json */
            $json = $options['json'];

            return $json;
        }

        if (isset($options['body']) && is_string($options['body'])) {
            $decoded = json_decode($options['body'], true);
            if (is_array($decoded)) {
                /** @var array<string, string> $decoded */
                return $decoded;
            }
        }

        return [];
    }
}
