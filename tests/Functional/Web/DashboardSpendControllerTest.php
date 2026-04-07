<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Spend;
use App\Entity\Currency;
use App\Entity\SpendingPlan;
use App\Entity\User;
use App\Redis\RedisDataKey;
use App\Service\RedisStore;
use App\Tests\Fixtures\BaseCurrenciesFixture;
use App\Tests\Fixtures\BaseSpendsFixture;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Fixtures\CurrentMonthSpendingPlanFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class DashboardSpendControllerTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return match ($testName) {
            'testDashboardShowsSpendWidgetFromStoredRecords',
            'testSpendsPageSupportsFiltersAndPagination',
            'testUserCanEditSpendFromSpendsPage',
            'testUserCanDeleteSpendFromSpendsPage',
            'testUserCanEditOtherUsersSpendFromSpendsPage' => [
                BaseCurrenciesFixture::class,
                BaseUsersFixture::class,
                CurrentMonthSpendingPlanFixture::class,
                BaseSpendsFixture::class,
            ],
            default => [
                BaseCurrenciesFixture::class,
                BaseUsersFixture::class,
                CurrentMonthSpendingPlanFixture::class,
            ],
        };
    }

    public function testUserCanAddSpendFromDashboard(): void
    {
        $this->loginAs(BaseUsersFixture::TEST_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard');
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

        $form = $crawler->selectButton('Add spend')->form([
            'dashboard_spend[amount]' => '37.20',
            'dashboard_spend[currency]' => $currencyValue,
            'dashboard_spend[spendingPlan]' => $planValue,
            'dashboard_spend[spendDate]' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'dashboard_spend[comment]' => 'Coffee and lunch',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/dashboard');

        $this->entityManager->clear();
        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy([
            'comment' => 'Coffee and lunch',
        ]);
        self::assertInstanceOf(Spend::class, $spend);
        self::assertSame('37.20', $spend->getAmount());
        self::assertSame('GEL', $spend->getCurrency()?->getCode());
        self::assertSame('March base plan', $spend->getSpendingPlan()?->getName());

        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('37.20 GEL', $crawler->text(''));

        $redisStore = static::getContainer()->get(RedisStore::class);
        $snapshot = $redisStore->getJsonByDataKey(
            RedisDataKey::MONTHLY_BALANCE_SNAPSHOT,
            ['monthKey' => (new \DateTimeImmutable())->format('Y-m')]
        );
        self::assertIsArray($snapshot);
        self::assertSame('37.20', (string) ($snapshot['monthSpentGel'] ?? ''));
    }

    public function testDashboardShowsSpendWidgetFromStoredRecords(): void
    {
        $this->loginAs(BaseUsersFixture::TEST_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Spent this month', $crawler->text(''));
        self::assertStringContainsString('Groceries basket', $crawler->text(''));
        self::assertSame(1, $crawler->filter('a[href="/dashboard/spends"]')->count());
    }

    public function testSpendsPageSupportsFiltersAndPagination(): void
    {
        $this->loginAs(BaseUsersFixture::TEST_USERNAME);

        $crawler = $this->client->request('GET', '/dashboard/spends');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('a.sp-tab.is-active[href*="view=stream"]')->count());
        self::assertStringContainsString('Groceries basket', $crawler->text(''));
        self::assertStringContainsString('Taxi and metro', $crawler->text(''));

        $crawler = $this->client->request('GET', '/dashboard/spends?q=Taxi');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Taxi and metro', $crawler->text(''));
        self::assertStringNotContainsString('Groceries basket', $crawler->text(''));

        $crawler = $this->client->request('GET', '/dashboard/spends?view=table&perPage=1&page=2');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Groceries basket', $crawler->text(''));
        self::assertStringNotContainsString('Taxi and metro', $crawler->text(''));
    }

    public function testDashboardSpendWidgetShowsOtherUsersSpends(): void
    {
        $otherUser = $this->entityManager->getRepository(User::class)->findOneBy([
            'username' => BaseUsersFixture::INCOMER_USERNAME,
        ]);
        self::assertInstanceOf(User::class, $otherUser);

        $plan = $this->entityManager->getRepository(SpendingPlan::class)->findOneBy([
            'name' => 'March base plan',
        ]);
        self::assertInstanceOf(SpendingPlan::class, $plan);

        $currency = $this->entityManager->getRepository(Currency::class)->findOneBy([
            'code' => 'GEL',
        ]);
        self::assertInstanceOf(Currency::class, $currency);

        $otherSpend = (new Spend())
            ->setUserAdded($otherUser)
            ->setSpendingPlan($plan)
            ->setAmount('999.99')
            ->setCurrency($currency)
            ->setSpendDate((new \DateTimeImmutable('today'))->setTime(0, 0))
            ->setComment('Other user private spend')
            ->setCreatedAt(new \DateTimeImmutable('now'));

        $this->entityManager->persist($otherSpend);
        $this->entityManager->flush();

        $this->loginAs(BaseUsersFixture::TEST_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Other user private spend', $crawler->text(''));

        $crawler = $this->client->request('GET', '/dashboard/spends');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Other user private spend', $crawler->text(''));
    }

    public function testUserCanEditSpendFromSpendsPage(): void
    {
        $this->loginAs(BaseUsersFixture::TEST_USERNAME);

        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy(['comment' => 'Groceries basket']);
        self::assertInstanceOf(Spend::class, $spend);

        $crawler = $this->client->request('GET', '/dashboard/spends/'.$spend->getId().'/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form([
            'dashboard_spend[amount]' => '99.90',
            'dashboard_spend[currency]' => (string) $spend->getCurrency()?->getId(),
            'dashboard_spend[spendingPlan]' => (string) $spend->getSpendingPlan()?->getId(),
            'dashboard_spend[spendDate]' => $spend->getSpendDate()->format('Y-m-d'),
            'dashboard_spend[comment]' => 'Groceries basket updated',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/dashboard/spends?month='.(new \DateTimeImmutable('first day of this month'))->format('Y-m'));

        $this->entityManager->clear();
        $updatedSpend = $this->entityManager->getRepository(Spend::class)->find($spend->getId());
        self::assertInstanceOf(Spend::class, $updatedSpend);
        self::assertSame('99.90', $updatedSpend->getAmount());
        self::assertSame('Groceries basket updated', $updatedSpend->getComment());
    }

    public function testUserCanDeleteSpendFromSpendsPage(): void
    {
        $this->loginAs(BaseUsersFixture::TEST_USERNAME);

        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy(['comment' => 'Taxi and metro']);
        self::assertInstanceOf(Spend::class, $spend);

        $crawler = $this->client->request('GET', '/dashboard/spends');
        self::assertResponseIsSuccessful();

        $deleteForm = $crawler->filter(sprintf('form[action="/dashboard/spends/%d/delete"]', $spend->getId()))->first();
        self::assertSame(1, $deleteForm->count());
        $csrf = (string) $deleteForm->filter('input[name="_token"]')->attr('value');
        self::assertNotSame('', $csrf);

        $this->client->request('POST', '/dashboard/spends/'.$spend->getId().'/delete', [
            '_token' => $csrf,
        ]);

        self::assertResponseRedirects('/dashboard/spends?month='.(new \DateTimeImmutable('first day of this month'))->format('Y-m'));

        $this->entityManager->clear();
        $removedSpend = $this->entityManager->getRepository(Spend::class)->find($spend->getId());
        self::assertNull($removedSpend);
    }

    public function testUserCanEditOtherUsersSpendFromSpendsPage(): void
    {
        $otherUser = $this->entityManager->getRepository(User::class)->findOneBy([
            'username' => BaseUsersFixture::INCOMER_USERNAME,
        ]);
        self::assertInstanceOf(User::class, $otherUser);

        $plan = $this->entityManager->getRepository(SpendingPlan::class)->findOneBy([
            'name' => 'March base plan',
        ]);
        self::assertInstanceOf(SpendingPlan::class, $plan);

        $currency = $this->entityManager->getRepository(Currency::class)->findOneBy([
            'code' => 'GEL',
        ]);
        self::assertInstanceOf(Currency::class, $currency);

        $spend = (new Spend())
            ->setUserAdded($otherUser)
            ->setSpendingPlan($plan)
            ->setAmount('77.70')
            ->setCurrency($currency)
            ->setSpendDate((new \DateTimeImmutable('today'))->setTime(0, 0))
            ->setComment('Other user spend editable')
            ->setCreatedAt(new \DateTimeImmutable('now'));

        $this->entityManager->persist($spend);
        $this->entityManager->flush();

        $this->loginAs(BaseUsersFixture::TEST_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard/spends/'.$spend->getId().'/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form([
            'dashboard_spend[amount]' => '88.80',
            'dashboard_spend[currency]' => (string) $currency->getId(),
            'dashboard_spend[spendingPlan]' => (string) $plan->getId(),
            'dashboard_spend[spendDate]' => $spend->getSpendDate()->format('Y-m-d'),
            'dashboard_spend[comment]' => 'Other user spend edited',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/dashboard/spends?month='.(new \DateTimeImmutable('first day of this month'))->format('Y-m'));

        $this->entityManager->clear();
        $updated = $this->entityManager->getRepository(Spend::class)->find($spend->getId());
        self::assertInstanceOf(Spend::class, $updated);
        self::assertSame('88.80', $updated->getAmount());
        self::assertSame('Other user spend edited', $updated->getComment());
    }

    public function testUserCanAddSpendForPastAndFutureSpendingPlans(): void
    {
        $currency = $this->entityManager->getRepository(Currency::class)->findOneBy(['code' => 'GEL']);
        self::assertInstanceOf(Currency::class, $currency);

        $prevMonthStart = (new \DateTimeImmutable('first day of previous month'))->setTime(0, 0);
        $nextMonthStart = (new \DateTimeImmutable('first day of next month'))->setTime(0, 0);

        $pastPlan = (new SpendingPlan())
            ->setName('Past month groceries plan')
            ->setPlanType(SpendingPlan::PLAN_TYPE_REGULAR)
            ->setDateFrom($prevMonthStart)
            ->setDateTo($prevMonthStart->modify('last day of this month'))
            ->setLimitAmount('500.00')
            ->setCurrency($currency)
            ->setWeight(0)
            ->setIsSystem(false);
        $futurePlan = (new SpendingPlan())
            ->setName('Future month travel plan')
            ->setPlanType(SpendingPlan::PLAN_TYPE_PLANNED)
            ->setDateFrom($nextMonthStart)
            ->setDateTo($nextMonthStart->modify('last day of this month'))
            ->setLimitAmount('900.00')
            ->setCurrency($currency)
            ->setWeight(0)
            ->setIsSystem(false);
        $this->entityManager->persist($pastPlan);
        $this->entityManager->persist($futurePlan);
        $this->entityManager->flush();

        $this->loginAs(BaseUsersFixture::TEST_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        $pastPlanOption = $crawler->filterXPath(
            '//select[@name="dashboard_spend[spendingPlan]"]/option[contains(normalize-space(.), "Past month groceries plan")]'
        )->first();
        self::assertSame(1, $pastPlanOption->count());
        $pastPlanValue = (string) $pastPlanOption->attr('value');

        $futurePlanOption = $crawler->filterXPath(
            '//select[@name="dashboard_spend[spendingPlan]"]/option[contains(normalize-space(.), "Future month travel plan")]'
        )->first();
        self::assertSame(1, $futurePlanOption->count());
        $futurePlanValue = (string) $futurePlanOption->attr('value');

        $currencyOption = $crawler->filterXPath(
            '//select[@name="dashboard_spend[currency]"]/option[contains(normalize-space(.), "GEL")]'
        )->first();
        self::assertSame(1, $currencyOption->count());
        $currencyValue = (string) $currencyOption->attr('value');

        $this->client->submit($crawler->selectButton('Add spend')->form([
            'dashboard_spend[amount]' => '11.10',
            'dashboard_spend[currency]' => $currencyValue,
            'dashboard_spend[spendingPlan]' => $pastPlanValue,
            'dashboard_spend[spendDate]' => $prevMonthStart->modify('+2 day')->format('Y-m-d'),
            'dashboard_spend[comment]' => 'Past plan spend',
        ]));
        self::assertResponseRedirects('/dashboard');

        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Add spend')->form([
            'dashboard_spend[amount]' => '22.20',
            'dashboard_spend[currency]' => $currencyValue,
            'dashboard_spend[spendingPlan]' => $futurePlanValue,
            'dashboard_spend[spendDate]' => $nextMonthStart->modify('+3 day')->format('Y-m-d'),
            'dashboard_spend[comment]' => 'Future plan spend',
        ]));
        self::assertResponseRedirects('/dashboard');

        $this->entityManager->clear();
        $pastSpend = $this->entityManager->getRepository(Spend::class)->findOneBy(['comment' => 'Past plan spend']);
        self::assertInstanceOf(Spend::class, $pastSpend);
        self::assertSame('Past month groceries plan', $pastSpend->getSpendingPlan()?->getName());

        $futureSpend = $this->entityManager->getRepository(Spend::class)->findOneBy(['comment' => 'Future plan spend']);
        self::assertInstanceOf(Spend::class, $futureSpend);
        self::assertSame('Future month travel plan', $futureSpend->getSpendingPlan()?->getName());
    }

    public function testSpendFormPlansFollowPrioritySortingRules(): void
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

        $this->loginAs(BaseUsersFixture::TEST_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard');
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
