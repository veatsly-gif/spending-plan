<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\TelegramUser;
use App\Entity\User;
use App\Repository\TelegramUserRepository;
use App\Service\TelegramBotService;
use App\Service\TelegramMiniAppTokenService;
use App\Service\TelegramUpdateProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TelegramUpdateProcessorTest extends TestCase
{
    public function testIgnoresUpdateWithoutMessage(): void
    {
        $repository = $this->createTelegramUserRepositoryMock();
        $repository->expects($this->never())->method('findOneBy');

        $requests = [];
        $processor = $this->createProcessor($repository, $requests);
        $processor->process(['update_id' => 1]);

        self::assertCount(0, $requests);
    }

    public function testUnknownUserGetsRegistrationHintForStartAndOtherCommands(): void
    {
        $repository = $this->createTelegramUserRepositoryMock();
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['telegramId' => '48995172'])
            ->willReturn(null);
        $repository->expects($this->never())->method('save');

        $requests = [];
        $processor = $this->createProcessor($repository, $requests);
        $processor->process($this->buildUpdate('/start', 48995172, 48995172, 'Sergey', null));

        self::assertCount(1, $requests);
        self::assertSame(['chat_id' => '48995172', 'text' => 'For registration type /reg'], $requests[0]['json']);
    }

    public function testRegCreatesNewPendingTelegramUser(): void
    {
        $repository = $this->createTelegramUserRepositoryMock();
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['telegramId' => '42'])
            ->willReturn(null);
        $repository->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(static function (mixed $value): bool {
                    return $value instanceof TelegramUser
                        && '42' === $value->getTelegramId()
                        && 'John' === $value->getFirstName()
                        && 'Smith' === $value->getLastName()
                        && TelegramUser::STATUS_PENDING === $value->getStatus();
                }),
                true
            );

        $requests = [];
        $processor = $this->createProcessor($repository, $requests);
        $processor->process($this->buildUpdate('/reg', 42, 100500, 'John', 'Smith'));

        self::assertCount(1, $requests);
        self::assertSame(
            ['chat_id' => '100500', 'text' => 'Registration request sent. Please wait for admin approval.'],
            $requests[0]['json']
        );
    }

    public function testRegUpdatesExistingPendingUserWithoutChangingStatus(): void
    {
        $existing = (new TelegramUser())
            ->setTelegramId('42')
            ->setFirstName('Old')
            ->setLastName('Name')
            ->setStatus(TelegramUser::STATUS_PENDING);

        $repository = $this->createTelegramUserRepositoryMock();
        $repository->expects($this->once())->method('findOneBy')->willReturn($existing);
        $repository->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(static function (mixed $value): bool {
                    return $value instanceof TelegramUser
                        && TelegramUser::STATUS_PENDING === $value->getStatus()
                        && 'New' === $value->getFirstName()
                        && 'Last' === $value->getLastName();
                }),
                true
            );

        $requests = [];
        $processor = $this->createProcessor($repository, $requests);
        $processor->process($this->buildUpdate('/reg', 42, 42, 'New', 'Last'));

        self::assertCount(1, $requests);
        self::assertSame(
            ['chat_id' => '42', 'text' => 'Registration request already exists. Please wait for admin approval.'],
            $requests[0]['json']
        );
    }

    public function testRegReopensRejectedUserRequest(): void
    {
        $linkedUser = (new User())
            ->setUsername('linked')
            ->setRoles(['ROLE_USER'])
            ->setPassword('hash');

        $existing = (new TelegramUser())
            ->setTelegramId('777')
            ->setFirstName('Old')
            ->setLastName('Old')
            ->setUser($linkedUser)
            ->setStatus(TelegramUser::STATUS_REJECTED)
            ->setAuthorizedAt(new \DateTimeImmutable('-1 day'));

        $repository = $this->createTelegramUserRepositoryMock();
        $repository->expects($this->once())->method('findOneBy')->willReturn($existing);
        $repository->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(static function (mixed $value): bool {
                    return $value instanceof TelegramUser
                        && TelegramUser::STATUS_PENDING === $value->getStatus()
                        && null === $value->getUser()
                        && null === $value->getAuthorizedAt()
                        && 'Sergey' === $value->getFirstName();
                }),
                true
            );

        $requests = [];
        $processor = $this->createProcessor($repository, $requests);
        $processor->process($this->buildUpdate('/reg', 777, 777, 'Sergey', null));

        self::assertCount(1, $requests);
        self::assertSame(
            ['chat_id' => '777', 'text' => 'Registration request sent again. Please wait for admin approval.'],
            $requests[0]['json']
        );
    }

    public function testAuthorizedUserGetsStubMessage(): void
    {
        $existing = (new TelegramUser())
            ->setTelegramId('48995172')
            ->setFirstName('Sergey')
            ->setStatus(TelegramUser::STATUS_AUTHORIZED);

        $repository = $this->createTelegramUserRepositoryMock();
        $repository->expects($this->once())->method('findOneBy')->willReturn($existing);
        $repository->expects($this->never())->method('save');

        $requests = [];
        $processor = $this->createProcessor($repository, $requests);
        $processor->process($this->buildUpdate('hello', 48995172, 48995172, 'Sergey', null));

        self::assertCount(1, $requests);
        self::assertSame('48995172', $requests[0]['json']['chat_id']);
        self::assertSame(
            '👇',
            $requests[0]['json']['text']
        );
        self::assertSame(
            'Add spend',
            $requests[0]['json']['reply_markup']['inline_keyboard'][0][0]['text'] ?? null
        );
        self::assertSame(
            'https://example.test/telegram/mini/spend?token=test-token',
            $requests[0]['json']['reply_markup']['inline_keyboard'][0][0]['web_app']['url'] ?? null
        );
    }

    public function testAuthorizedUserUsesTelegramPublicHostWhenGeneratedUrlIsLocalOrHttp(): void
    {
        $previousEnv = getenv('TELEGRAM_PUBLIC_HOST');
        $previousServer = $_SERVER['TELEGRAM_PUBLIC_HOST'] ?? null;
        $previousDotenv = $_ENV['TELEGRAM_PUBLIC_HOST'] ?? null;

        putenv('TELEGRAM_PUBLIC_HOST=https://public.example.test');
        $_SERVER['TELEGRAM_PUBLIC_HOST'] = 'https://public.example.test';
        $_ENV['TELEGRAM_PUBLIC_HOST'] = 'https://public.example.test';

        try {
            $existing = (new TelegramUser())
                ->setTelegramId('48995172')
                ->setFirstName('Sergey')
                ->setStatus(TelegramUser::STATUS_AUTHORIZED);

            $repository = $this->createTelegramUserRepositoryMock();
            $repository->expects($this->once())->method('findOneBy')->willReturn($existing);
            $repository->expects($this->never())->method('save');

            $requests = [];
            $processor = $this->createProcessor($repository, $requests, 'http://localhost:8188/telegram/mini/spend?token=test-token');
            $processor->process($this->buildUpdate('hello', 48995172, 48995172, 'Sergey', null));

            self::assertCount(1, $requests);
            self::assertSame(
                'https://public.example.test/telegram/mini/spend?token=test-token',
                $requests[0]['json']['reply_markup']['inline_keyboard'][0][0]['web_app']['url'] ?? null
            );
        } finally {
            if (false === $previousEnv) {
                putenv('TELEGRAM_PUBLIC_HOST');
            } else {
                putenv(sprintf('TELEGRAM_PUBLIC_HOST=%s', $previousEnv));
            }

            if (null === $previousServer) {
                unset($_SERVER['TELEGRAM_PUBLIC_HOST']);
            } else {
                $_SERVER['TELEGRAM_PUBLIC_HOST'] = $previousServer;
            }

            if (null === $previousDotenv) {
                unset($_ENV['TELEGRAM_PUBLIC_HOST']);
            } else {
                $_ENV['TELEGRAM_PUBLIC_HOST'] = $previousDotenv;
            }
        }
    }

    /**
     * @param array<int, array{method: string, url: string, json: array<string, mixed>}> $requests
     */
    private function createProcessor(
        TelegramUserRepository $repository,
        array &$requests,
        string $generatedUrl = 'https://example.test/telegram/mini/spend?token=test-token',
    ): TelegramUpdateProcessor {
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $json = self::extractPayload($options);
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'json' => $json,
            ];

            return new MockResponse(
                json_encode(['ok' => true, 'result' => ['message_id' => 1]], JSON_THROW_ON_ERROR),
                ['http_code' => Response::HTTP_OK],
            );
        });

        $bot = new TelegramBotService($httpClient, new NullLogger(), 'test-token');
        $miniAppTokenService = new TelegramMiniAppTokenService('test-secret');
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn($generatedUrl);

        return new TelegramUpdateProcessor(
            $repository,
            $bot,
            $miniAppTokenService,
            $urlGenerator,
            new NullLogger()
        );
    }

    /**
     * @return MockObject&TelegramUserRepository
     */
    private function createTelegramUserRepositoryMock(): TelegramUserRepository
    {
        return $this->getMockBuilder(TelegramUserRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy', 'save'])
            ->getMock();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUpdate(string $text, int $fromId, int $chatId, string $firstName, ?string $lastName): array
    {
        return [
            'update_id' => random_int(1, 1000000),
            'message' => [
                'message_id' => 1,
                'from' => [
                    'id' => $fromId,
                    'is_bot' => false,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ],
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'date' => 1710000000,
                'text' => $text,
            ],
        ];
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
