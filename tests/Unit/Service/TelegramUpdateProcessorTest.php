<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\TelegramUser;
use App\Entity\User;
use App\Repository\TelegramUserRepository;
use App\Service\TelegramBotService;
use App\Service\TelegramUpdateProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

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
        self::assertSame(
            ['chat_id' => '48995172', 'text' => 'You are already registered. Please wait for a coming functionality'],
            $requests[0]['json']
        );
    }

    /**
     * @param array<int, array{method: string, url: string, json: array<string, string>}> $requests
     */
    private function createProcessor(TelegramUserRepository $repository, array &$requests): TelegramUpdateProcessor
    {
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

        return new TelegramUpdateProcessor($repository, $bot, new NullLogger());
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
