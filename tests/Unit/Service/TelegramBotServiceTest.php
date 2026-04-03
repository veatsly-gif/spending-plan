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
                'Telegram send failed: failed',
                $this->callback(static fn (array $context): bool => '48995172' === ($context['chat_id'] ?? null))
            );

        $service = new TelegramBotService($httpClient, $logger, 'test-token');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Telegram API sendMessage failed: failed');

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

    public function testSendMessageWithWebAppButtonSendsInlineKeyboardPayload(): void
    {
        $capturedPayload = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedPayload): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.telegram.org/botwebapp-token/sendMessage', $url);
            $capturedPayload = self::extractPayload($options);

            return new MockResponse(
                json_encode(['ok' => true, 'result' => ['message_id' => 1]], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $service = new TelegramBotService($httpClient, $logger, 'webapp-token');
        $service->sendMessageWithWebAppButton(
            '48995172',
            'Use mini-app',
            'Add spend',
            'https://example.test/telegram/mini/spend?token=abc',
        );

        self::assertSame('48995172', $capturedPayload['chat_id'] ?? null);
        self::assertSame('Use mini-app', $capturedPayload['text'] ?? null);
        self::assertSame(
            'Add spend',
            $capturedPayload['reply_markup']['inline_keyboard'][0][0]['text'] ?? null,
        );
        self::assertSame(
            'https://example.test/telegram/mini/spend?token=abc',
            $capturedPayload['reply_markup']['inline_keyboard'][0][0]['web_app']['url'] ?? null,
        );
    }

    public function testSendMessageWithInlineButtonsSendsCallbackKeyboardPayload(): void
    {
        $capturedPayload = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedPayload): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.telegram.org/botinline-token/sendMessage', $url);
            $capturedPayload = self::extractPayload($options);

            return new MockResponse(
                json_encode(['ok' => true, 'result' => ['message_id' => 1]], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $service = new TelegramBotService($httpClient, $logger, 'inline-token');
        $service->sendMessageWithInlineButtons(
            '48995172',
            'Please confirm',
            [
                ['label' => 'Already done', 'callback_data' => 'nf|a|2026-04|done'],
                ['label' => 'Remind me later', 'callback_data' => 'nf|a|2026-04|remind_later'],
            ],
        );

        self::assertSame('Please confirm', $capturedPayload['text'] ?? null);
        self::assertSame(
            'Already done',
            $capturedPayload['reply_markup']['inline_keyboard'][0][0]['text'] ?? null
        );
        self::assertSame(
            'nf|a|2026-04|done',
            $capturedPayload['reply_markup']['inline_keyboard'][0][0]['callback_data'] ?? null
        );
    }

    public function testAnswerCallbackQueryUsesTelegramEndpoint(): void
    {
        $capturedMethod = null;
        $capturedUrl = null;
        $capturedPayload = [];

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$capturedMethod, &$capturedUrl, &$capturedPayload): MockResponse {
            $capturedMethod = $method;
            $capturedUrl = $url;
            $capturedPayload = self::extractPayload($options);

            return new MockResponse(
                json_encode(['ok' => true, 'result' => true], JSON_THROW_ON_ERROR),
                ['http_code' => 200],
            );
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $service = new TelegramBotService($httpClient, $logger, 'cb-token');
        $service->answerCallbackQuery('callback-123', 'Done');

        self::assertSame('POST', $capturedMethod);
        self::assertSame('https://api.telegram.org/botcb-token/answerCallbackQuery', $capturedUrl);
        self::assertSame('callback-123', $capturedPayload['callback_query_id'] ?? null);
        self::assertSame('Done', $capturedPayload['text'] ?? null);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function extractPayload(array $options): array
    {
        if (isset($options['json']) && is_array($options['json'])) {
            /** @var array<string, mixed> $json */
            $json = $options['json'];

            return $json;
        }

        if (isset($options['body']) && is_string($options['body'])) {
            $decoded = json_decode($options['body'], true);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        }

        return [];
    }
}
