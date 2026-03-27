<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Spend;
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
            'testSpendsPageSupportsFiltersAndPagination' => [
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
        self::assertStringContainsString('2 records', $crawler->text(''));
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
}
