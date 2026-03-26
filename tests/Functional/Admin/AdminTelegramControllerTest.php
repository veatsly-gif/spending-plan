<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\TelegramUser;
use App\Tests\Fixtures\AuthorizedTelegramUserFixture;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Fixtures\PendingTelegramUserFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class AdminTelegramControllerTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return match ($testName) {
            'testAdminCanRejectPendingTelegramRegistration' => [
                BaseUsersFixture::class,
                PendingTelegramUserFixture::class,
            ],
            'testAuthorizedTelegramUserIsNotShownInPendingActions' => [
                BaseUsersFixture::class,
                AuthorizedTelegramUserFixture::class,
            ],
            default => [],
        };
    }

    public function testAdminCanRejectPendingTelegramRegistration(): void
    {
        $telegramUser = $this->entityManager
            ->getRepository(TelegramUser::class)
            ->findOneBy(['telegramId' => PendingTelegramUserFixture::TELEGRAM_ID]);
        self::assertInstanceOf(TelegramUser::class, $telegramUser);

        $this->loginAs('admin');
        $crawler = $this->client->request('GET', '/admin/telegram');
        $form = $crawler->filter('form[action="/admin/telegram/'.$telegramUser->getId().'/reject"]')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/telegram');

        $this->entityManager->clear();
        /** @var TelegramUser|null $reloaded */
        $reloaded = $this->entityManager->getRepository(TelegramUser::class)->find($telegramUser->getId());
        self::assertInstanceOf(TelegramUser::class, $reloaded);
        self::assertSame(TelegramUser::STATUS_REJECTED, $reloaded->getStatus());
        self::assertNull($reloaded->getUser());
        self::assertNull($reloaded->getAuthorizedAt());
    }

    public function testAuthorizedTelegramUserIsNotShownInPendingActions(): void
    {
        $telegramUser = $this->entityManager
            ->getRepository(TelegramUser::class)
            ->findOneBy(['telegramId' => AuthorizedTelegramUserFixture::TELEGRAM_ID]);
        self::assertInstanceOf(TelegramUser::class, $telegramUser);

        $this->loginAs('admin');
        $crawler = $this->client->request('GET', '/admin/telegram');

        self::assertResponseIsSuccessful();
        self::assertSame(
            0,
            $crawler->filter('form[action="/admin/telegram/'.$telegramUser->getId().'/reject"]')->count()
        );
    }
}
