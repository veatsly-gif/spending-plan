<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Currency;
use App\Entity\Spend;
use App\Entity\SpendingPlan;
use App\Redis\RedisDataKey;
use App\Service\RedisStore;
use App\Service\TelegramMiniAppTokenService;
use App\Tests\Fixtures\AuthorizedTelegramUserFixture;
use App\Tests\Fixtures\BaseCurrenciesFixture;
use App\Tests\Fixtures\BaseSpendsFixture;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Fixtures\CurrentMonthSpendingPlanFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class TelegramMiniAppSpendControllerTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        $fixtures = [
            BaseCurrenciesFixture::class,
            BaseUsersFixture::class,
            CurrentMonthSpendingPlanFixture::class,
            AuthorizedTelegramUserFixture::class,
        ];

        if (in_array($testName, [
            'testMiniAppShellLoadsReactRoot',
            'testMiniAppSpendsListJson',
            'testAuthorizedTelegramUserCanCreateSpendViaMiniApi',
            'testAuthorizedTelegramUserCanEditSpendViaMiniApi',
            'testAuthorizedTelegramUserCanDeleteSpendViaMiniApi',
            'testMiniAppSpendFormPlansFollowPrioritySortingRules',
            'testMiniAppPreferencesPersistToMetadata',
        ], true)) {
            $fixtures[] = BaseSpendsFixture::class;
        }

        return $fixtures;
    }

    public function testMiniAppRejectsInvalidToken(): void
    {
        $this->client->request('GET', '/telegram/mini/spend?token=invalid');

        self::assertResponseStatusCodeSame(403);
    }

    public function testMiniAppShellLoadsReactRoot(): void
    {
        $tokenService = static::getContainer()->get(TelegramMiniAppTokenService::class);
        $token = $tokenService->generateToken(AuthorizedTelegramUserFixture::TELEGRAM_ID);

        $crawler = $this->client->request('GET', '/telegram/mini/spend?token='.urlencode($token));
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('#telegram-mini-root')->count());
    }

    public function testMiniAppSpendsListJson(): void
    {
        $tokenService = static::getContainer()->get(TelegramMiniAppTokenService::class);
        $token = $tokenService->generateToken(AuthorizedTelegramUserFixture::TELEGRAM_ID);

        $this->client->request('GET', '/api/telegram/mini/spends?token='.urlencode($token));
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['success'] ?? false);
        self::assertArrayHasKey('payload', $payload);
        self::assertNotEmpty($payload['payload']['spends'] ?? []);
    }

    public function testAuthorizedTelegramUserCanCreateSpendViaMiniApi(): void
    {
        $tokenService = static::getContainer()->get(TelegramMiniAppTokenService::class);
        $token = $tokenService->generateToken(AuthorizedTelegramUserFixture::TELEGRAM_ID);

        $bootstrap = $this->jsonGet('/api/telegram/mini/bootstrap?token='.urlencode($token));
        self::assertTrue($bootstrap['success'] ?? false);
        $defaults = $bootstrap['overview']['forms']['spend']['defaults'] ?? [];
        self::assertNotEmpty($defaults);

        $gelId = null;
        foreach ($bootstrap['overview']['forms']['spend']['currencies'] ?? [] as $currency) {
            if (($currency['code'] ?? '') === 'GEL') {
                $gelId = (int) ($currency['id'] ?? 0);
                break;
            }
        }
        self::assertNotSame(0, $gelId);

        $planId = (int) ($defaults['spendingPlanId'] ?? 0);
        self::assertGreaterThan(0, $planId);

        $this->client->request(
            'POST',
            '/api/telegram/mini/spends?token='.urlencode($token),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'amount' => '45.90',
                'currencyId' => $gelId,
                'spendingPlanId' => $planId,
                'spendDate' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                'comment' => 'Mini app spend',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();
        $created = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertTrue($created['success'] ?? false);

        $this->entityManager->clear();
        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy([
            'comment' => 'Mini app spend',
        ]);
        self::assertInstanceOf(Spend::class, $spend);
        self::assertSame('45.90', $spend->getAmount());
        self::assertSame('GEL', $spend->getCurrency()?->getCode());

        $redisStore = static::getContainer()->get(RedisStore::class);
        $snapshot = $redisStore->getJsonByDataKey(
            RedisDataKey::MONTHLY_BALANCE_SNAPSHOT,
            ['monthKey' => (new \DateTimeImmutable())->format('Y-m')]
        );
        self::assertIsArray($snapshot);
        self::assertGreaterThanOrEqual(45.90, (float) ($snapshot['monthSpentGel'] ?? 0));
    }

    public function testAuthorizedTelegramUserCanEditSpendViaMiniApi(): void
    {
        $tokenService = static::getContainer()->get(TelegramMiniAppTokenService::class);
        $token = $tokenService->generateToken(AuthorizedTelegramUserFixture::TELEGRAM_ID);

        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy(['comment' => 'Groceries basket']);
        self::assertInstanceOf(Spend::class, $spend);

        $this->client->request(
            'PUT',
            '/api/telegram/mini/spends/'.$spend->getId().'?token='.urlencode($token),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'amount' => '101.15',
                'currencyId' => (int) $spend->getCurrency()?->getId(),
                'spendingPlanId' => (int) $spend->getSpendingPlan()?->getId(),
                'spendDate' => $spend->getSpendDate()->format('Y-m-d'),
                'comment' => 'Groceries basket mini edited',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertTrue($payload['success'] ?? false);

        $this->entityManager->clear();
        $updatedSpend = $this->entityManager->getRepository(Spend::class)->find($spend->getId());
        self::assertInstanceOf(Spend::class, $updatedSpend);
        self::assertSame('101.15', $updatedSpend->getAmount());
        self::assertSame('Groceries basket mini edited', $updatedSpend->getComment());
    }

    public function testAuthorizedTelegramUserCanDeleteSpendViaMiniApi(): void
    {
        $tokenService = static::getContainer()->get(TelegramMiniAppTokenService::class);
        $token = $tokenService->generateToken(AuthorizedTelegramUserFixture::TELEGRAM_ID);

        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy(['comment' => 'Taxi and metro']);
        self::assertInstanceOf(Spend::class, $spend);
        $spendId = (int) $spend->getId();

        $this->client->request(
            'DELETE',
            '/api/telegram/mini/spends/'.$spendId.'?token='.urlencode($token)
        );
        self::assertResponseIsSuccessful();

        $this->entityManager->clear();
        $removedSpend = $this->entityManager->getRepository(Spend::class)->find($spendId);
        self::assertNull($removedSpend);
    }

    public function testMiniAppSpendFormPlansFollowPrioritySortingRules(): void
    {
        $currency = $this->entityManager->getRepository(Currency::class)->findOneBy(['code' => 'GEL']);
        self::assertInstanceOf(Currency::class, $currency);

        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);
        $monthStart = $today->modify('first day of this month');
        $monthEnd = $today->modify('last day of this month');

        $this->entityManager->persist(
            (new SpendingPlan())
                ->setName("Dima's birthday")
                ->setPlanType(SpendingPlan::PLAN_TYPE_CUSTOM)
                ->setDateFrom($today)
                ->setDateTo($today)
                ->setLimitAmount('100.00')
                ->setCurrency($currency)
                ->setWeight(2)
                ->setIsSystem(false)
        );
        $this->entityManager->persist(
            (new SpendingPlan())
                ->setName('Пятничка')
                ->setPlanType(SpendingPlan::PLAN_TYPE_WEEKDAY)
                ->setDateFrom($today)
                ->setDateTo($today)
                ->setLimitAmount('10.00')
                ->setCurrency($currency)
                ->setWeight(1)
                ->setIsSystem(false)
        );
        $this->entityManager->persist(
            (new SpendingPlan())
                ->setName('Planned spends')
                ->setPlanType(SpendingPlan::PLAN_TYPE_PLANNED)
                ->setDateFrom($monthStart)
                ->setDateTo($monthEnd)
                ->setLimitAmount('200.00')
                ->setCurrency($currency)
                ->setWeight(0)
                ->setIsSystem(false)
        );
        $this->entityManager->persist(
            (new SpendingPlan())
                ->setName('Выхи 4-5 апреля')
                ->setPlanType(SpendingPlan::PLAN_TYPE_WEEKEND)
                ->setDateFrom($today->modify('+1 day'))
                ->setDateTo($today->modify('+2 day'))
                ->setLimitAmount('20.00')
                ->setCurrency($currency)
                ->setWeight(0)
                ->setIsSystem(false)
        );
        $this->entityManager->persist(
            (new SpendingPlan())
                ->setName('Будни 1-2 апреля')
                ->setPlanType(SpendingPlan::PLAN_TYPE_WEEKDAY)
                ->setDateFrom($monthStart)
                ->setDateTo($monthStart->modify('+1 day'))
                ->setLimitAmount('20.00')
                ->setCurrency($currency)
                ->setWeight(0)
                ->setIsSystem(false)
        );
        $this->entityManager->flush();

        $tokenService = static::getContainer()->get(TelegramMiniAppTokenService::class);
        $token = $tokenService->generateToken(AuthorizedTelegramUserFixture::TELEGRAM_ID);
        $date = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $payload = $this->jsonGet('/api/telegram/mini/spend-form?token='.urlencode($token).'&spendDate='.urlencode($date));
        self::assertTrue($payload['success'] ?? false);
        $plans = $payload['payload']['spendingPlans'] ?? [];
        self::assertGreaterThanOrEqual(6, count($plans));

        $labels = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $plans);
        self::assertStringStartsWith("Dima's birthday", $labels[0] ?? '');
        self::assertStringStartsWith('Пятничка', $labels[1] ?? '');
        self::assertStringStartsWith('Выхи 4-5 апреля', $labels[2] ?? '');
        self::assertStringStartsWith('March base plan', $labels[3] ?? '');
        self::assertStringStartsWith('Planned spends', $labels[4] ?? '');
        self::assertStringStartsWith('Будни 1-2 апреля', $labels[5] ?? '');
    }

    public function testMiniAppPreferencesPersistToMetadata(): void
    {
        $tokenService = static::getContainer()->get(TelegramMiniAppTokenService::class);
        $token = $tokenService->generateToken(AuthorizedTelegramUserFixture::TELEGRAM_ID);

        $this->client->request(
            'POST',
            '/api/telegram/mini/preferences?token='.urlencode($token),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['language' => 'ru', 'theme' => 'dark'], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();
        $out = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertTrue($out['success'] ?? false);
        self::assertSame('ru', $out['preferences']['language'] ?? '');
        self::assertSame('dark', $out['preferences']['theme'] ?? '');

        $get = $this->jsonGet('/api/telegram/mini/preferences?token='.urlencode($token));
        self::assertSame('ru', $get['preferences']['language'] ?? '');
        self::assertSame('dark', $get['preferences']['theme'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonGet(string $uri): array
    {
        $this->client->request('GET', $uri);
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
