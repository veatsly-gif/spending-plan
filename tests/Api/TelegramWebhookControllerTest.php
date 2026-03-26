<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\TelegramUser;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Fixtures\RejectedTelegramUserFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class TelegramWebhookControllerTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return match ($testName) {
            'testRegCommandReopensRejectedTelegramUser' => [
                BaseUsersFixture::class,
                RejectedTelegramUserFixture::class,
            ],
            default => [BaseUsersFixture::class],
        };
    }

    public function testWebhookRejectsInvalidSecret(): void
    {
        $this->client->request(
            'POST',
            '/api/telegram/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'wrong-secret',
            ],
            content: json_encode(['update_id' => 1], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
        self::assertJsonStringEqualsJsonString(
            '{"ok":false,"reason":"forbidden"}',
            (string) $this->client->getResponse()->getContent()
        );
    }

    public function testWebhookRejectsInvalidJsonPayload(): void
    {
        $this->client->request(
            'POST',
            '/api/telegram/webhook',
            server: $this->webhookHeaders(),
            content: 'not-a-json',
        );

        self::assertResponseStatusCodeSame(400);
        self::assertJsonStringEqualsJsonString(
            '{"ok":false,"reason":"invalid_payload"}',
            (string) $this->client->getResponse()->getContent()
        );
    }

    public function testRegCommandCreatesPendingTelegramUser(): void
    {
        $this->client->request(
            'POST',
            '/api/telegram/webhook',
            server: $this->webhookHeaders(),
            content: json_encode($this->buildUpdate('/reg', 48995172, 48995172, 'Sergey', 'Sheps'), JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();

        /** @var TelegramUser|null $telegramUser */
        $telegramUser = $this->entityManager->getRepository(TelegramUser::class)->findOneBy(['telegramId' => '48995172']);
        self::assertInstanceOf(TelegramUser::class, $telegramUser);
        self::assertSame(TelegramUser::STATUS_PENDING, $telegramUser->getStatus());
        self::assertSame('Sergey', $telegramUser->getFirstName());
        self::assertSame('Sheps', $telegramUser->getLastName());
    }

    public function testRegCommandReopensRejectedTelegramUser(): void
    {
        $rejected = $this->entityManager
            ->getRepository(TelegramUser::class)
            ->findOneBy(['telegramId' => RejectedTelegramUserFixture::TELEGRAM_ID]);
        self::assertInstanceOf(TelegramUser::class, $rejected);
        self::assertSame(TelegramUser::STATUS_REJECTED, $rejected->getStatus());

        $this->client->request(
            'POST',
            '/api/telegram/webhook',
            server: $this->webhookHeaders(),
            content: json_encode(
                $this->buildUpdate('/reg', (int) RejectedTelegramUserFixture::TELEGRAM_ID, (int) RejectedTelegramUserFixture::TELEGRAM_ID, 'New', 'User'),
                JSON_THROW_ON_ERROR
            ),
        );

        self::assertResponseIsSuccessful();

        $this->entityManager->clear();
        /** @var TelegramUser|null $reloaded */
        $reloaded = $this->entityManager->getRepository(TelegramUser::class)->find($rejected->getId());
        self::assertInstanceOf(TelegramUser::class, $reloaded);
        self::assertSame(TelegramUser::STATUS_PENDING, $reloaded->getStatus());
        self::assertSame('New', $reloaded->getFirstName());
        self::assertNull($reloaded->getUser());
        self::assertNull($reloaded->getAuthorizedAt());
    }

    /**
     * @return array<string, string>
     */
    private function webhookHeaders(): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'test-webhook-secret',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUpdate(string $text, int $fromId, int $chatId, string $firstName, ?string $lastName): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
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
}
