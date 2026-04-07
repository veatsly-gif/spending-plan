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
            'testMiniAppShowsSpendsListAndActions',
            'testAuthorizedTelegramUserCanEditSpendFromMiniApp',
            'testAuthorizedTelegramUserCanDeleteSpendFromMiniApp',
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

    public function testAuthorizedTelegramUserCanAddSpendFromMiniApp(): void
    {
        $tokenService = static::getContainer()->get(TelegramMiniAppTokenService::class);
        $token = $tokenService->generateToken(AuthorizedTelegramUserFixture::TELEGRAM_ID);

        $crawler = $this->client->request('GET', '/telegram/mini/spend?token='.urlencode($token));
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('form[name="dashboard_spend"]')->count());

        $gelOption = $crawler->filterXPath(
            '//select[@name="dashboard_spend[currency]"]'
            .'/option[contains(normalize-space(.), "GEL")]'
        )->first();
        self::assertSame(1, $gelOption->count());
        $currencyValue = (string) $gelOption->attr('value');

        $planOption = $crawler->filterXPath(
            '//select[@name="dashboard_spend[spendingPlan]"]'
            .'/option[contains(normalize-space(.), "March base plan")]'
        )->first();
        self::assertSame(1, $planOption->count());
        $planValue = (string) $planOption->attr('value');

        $form = $crawler->filter('form[name="dashboard_spend"]')->form([
            'dashboard_spend[amount]' => '45.90',
            'dashboard_spend[currency]' => $currencyValue,
            'dashboard_spend[spendingPlan]' => $planValue,
            'dashboard_spend[spendDate]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'dashboard_spend[comment]' => 'Mini app spend',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/telegram/mini/spend?token='.urlencode($token));

        $this->entityManager->clear();
        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy([
            'comment' => 'Mini app spend',
        ]);
        self::assertInstanceOf(Spend::class, $spend);
        self::assertSame('45.90', $spend->getAmount());
        self::assertSame('GEL', $spend->getCurrency()?->getCode());

        $crawler = $this->client->request('GET', '/telegram/mini/spend?token='.urlencode($token));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('45.90 GEL', $crawler->text(''));

        $redisStore = static::getContainer()->get(RedisStore::class);
        $snapshot = $redisStore->getJsonByDataKey(
            RedisDataKey::MONTHLY_BALANCE_SNAPSHOT,
            ['monthKey' => (new \DateTimeImmutable())->format('Y-m')]
        );
        self::assertIsArray($snapshot);
        self::assertSame('45.90', (string) ($snapshot['monthSpentGel'] ?? ''));
    }

    public function testMiniAppShowsSpendsListAndActions(): void
    {
        $tokenService = static::getContainer()->get(TelegramMiniAppTokenService::class);
        $token = $tokenService->generateToken(AuthorizedTelegramUserFixture::TELEGRAM_ID);

        $crawler = $this->client->request('GET', '/telegram/mini/spend?token='.urlencode($token));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Groceries basket', $crawler->text(''));
        self::assertGreaterThan(
            0,
            $crawler->filter('a[href*="/telegram/mini/spends/"][href*="/edit?token="]')->count()
        );

        $crawler = $this->client->request('GET', '/telegram/mini/spend?token='.urlencode($token).'&tab=spends');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('a.mini-view-toggle-btn.is-stream[href*="view=table"]')->count());
        self::assertGreaterThan(0, $crawler->filter('.spend-stream-group')->count());
    }

    public function testAuthorizedTelegramUserCanEditSpendFromMiniApp(): void
    {
        $tokenService = static::getContainer()->get(TelegramMiniAppTokenService::class);
        $token = $tokenService->generateToken(AuthorizedTelegramUserFixture::TELEGRAM_ID);

        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy(['comment' => 'Groceries basket']);
        self::assertInstanceOf(Spend::class, $spend);

        $crawler = $this->client->request(
            'GET',
            '/telegram/mini/spends/'.$spend->getId().'/edit?token='.urlencode($token)
        );
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form([
            'dashboard_spend[amount]' => '101.15',
            'dashboard_spend[currency]' => (string) $spend->getCurrency()?->getId(),
            'dashboard_spend[spendingPlan]' => (string) $spend->getSpendingPlan()?->getId(),
            'dashboard_spend[spendDate]' => $spend->getSpendDate()->format('Y-m-d'),
            'dashboard_spend[comment]' => 'Groceries basket mini edited',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/telegram/mini/spend?token='.urlencode($token));

        $this->entityManager->clear();
        $updatedSpend = $this->entityManager->getRepository(Spend::class)->find($spend->getId());
        self::assertInstanceOf(Spend::class, $updatedSpend);
        self::assertSame('101.15', $updatedSpend->getAmount());
        self::assertSame('Groceries basket mini edited', $updatedSpend->getComment());
    }

    public function testAuthorizedTelegramUserCanDeleteSpendFromMiniApp(): void
    {
        $tokenService = static::getContainer()->get(TelegramMiniAppTokenService::class);
        $token = $tokenService->generateToken(AuthorizedTelegramUserFixture::TELEGRAM_ID);

        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy(['comment' => 'Taxi and metro']);
        self::assertInstanceOf(Spend::class, $spend);

        $crawler = $this->client->request('GET', '/telegram/mini/spend?token='.urlencode($token));
        self::assertResponseIsSuccessful();

        $action = '/telegram/mini/spends/'.$spend->getId().'/delete?token='.urlencode($token);
        $deleteForm = $crawler->filter(sprintf('form[action="%s"]', $action))->first();
        self::assertSame(1, $deleteForm->count());
        $csrf = (string) $deleteForm->filter('input[name="_token"]')->attr('value');
        self::assertNotSame('', $csrf);

        $this->client->request('POST', $action, [
            '_token' => $csrf,
        ]);

        self::assertResponseRedirects('/telegram/mini/spend?token='.urlencode($token));

        $this->entityManager->clear();
        $removedSpend = $this->entityManager->getRepository(Spend::class)->find($spend->getId());
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
        $crawler = $this->client->request('GET', '/telegram/mini/spend?token='.urlencode($token));
        self::assertResponseIsSuccessful();

        $options = $crawler->filterXPath(
            '//select[@name="dashboard_spend[spendingPlan]"]/option[@value!=""]'
        );
        self::assertGreaterThanOrEqual(6, $options->count());

        $labels = [];
        foreach ($options as $node) {
            $labels[] = trim((string) $node->textContent);
        }

        self::assertStringStartsWith("Dima's birthday", $labels[0] ?? '');
        self::assertStringStartsWith('Пятничка', $labels[1] ?? '');
        self::assertStringStartsWith('Выхи 4-5 апреля', $labels[2] ?? '');
        self::assertStringStartsWith('March base plan', $labels[3] ?? '');
        self::assertStringStartsWith('Planned spends', $labels[4] ?? '');
        self::assertStringStartsWith('Будни 1-2 апреля', $labels[5] ?? '');
    }
}
