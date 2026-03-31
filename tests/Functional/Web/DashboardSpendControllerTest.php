<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Spend;
use App\Entity\Currency;
use App\Entity\SpendingPlan;
use App\Entity\User;
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
            'testUserCanDeleteSpendFromSpendsPage' => [
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
        self::assertStringContainsString('Groceries basket', $crawler->text(''));
        self::assertStringContainsString('Taxi and metro', $crawler->text(''));

        $crawler = $this->client->request('GET', '/dashboard/spends?q=Taxi');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Taxi and metro', $crawler->text(''));
        self::assertStringNotContainsString('Groceries basket', $crawler->text(''));

        $crawler = $this->client->request('GET', '/dashboard/spends?perPage=1&page=2');
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
}
