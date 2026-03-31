<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Income;
use App\Redis\RedisDataKey;
use App\Service\RedisStore;
use App\Tests\Fixtures\BaseCurrenciesFixture;
use App\Tests\Fixtures\BaseIncomesFixture;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class DashboardIncomeControllerTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return match ($testName) {
            'testDashboardShowsIncomeWidgetFromStoredRecords' => [
                BaseCurrenciesFixture::class,
                BaseUsersFixture::class,
                BaseIncomesFixture::class,
            ],
            'testDashboardIncomeTotalsAreSharedForAllUsers' => [
                BaseCurrenciesFixture::class,
                BaseUsersFixture::class,
                BaseIncomesFixture::class,
            ],
            'testIncomesPageShowsIncomeList' => [
                BaseCurrenciesFixture::class,
                BaseUsersFixture::class,
                BaseIncomesFixture::class,
            ],
            default => [
                BaseCurrenciesFixture::class,
                BaseUsersFixture::class,
            ],
        };
    }

    public function testIncomerCanAddIncomeWithConversion(): void
    {
        $redisStore = static::getContainer()->get(RedisStore::class);
        $redisStore->setJsonByDataKey(RedisDataKey::INCOME_RATES_LIVE, [], [
            'eurGel' => '2.500000',
            'usdtGel' => '2.700000',
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        $this->loginAs(BaseUsersFixture::INCOMER_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(
            0,
            $crawler->filter('form[name="dashboard_income"]')->count()
        );

        $eurOption = $crawler->filterXPath(
            '//select[@name="dashboard_income[currency]"]'
            .'/option[contains(normalize-space(.), "EUR")]'
        )->first();
        self::assertSame(1, $eurOption->count());
        $eurValue = (string) $eurOption->attr('value');

        $form = $crawler->selectButton('Add income')->form([
            'dashboard_income[amount]' => '10',
            'dashboard_income[currency]' => $eurValue,
            'dashboard_income[comment]' => 'Salary',
            'dashboard_income[convertToGel]' => 1,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/dashboard');

        $this->entityManager->clear();
        $income = $this->entityManager->getRepository(Income::class)->findOneBy([
            'comment' => 'Salary',
        ]);
        self::assertInstanceOf(Income::class, $income);
        self::assertSame('10.00', $income->getAmount());
        self::assertSame('EUR', $income->getCurrency()?->getCode());
        self::assertSame('25.00', $income->getAmountInGel());
        self::assertSame('2.5000', $income->getRate());
    }

    public function testRegularUserCannotSeeIncomeCreateForm(): void
    {
        $this->loginAs(BaseUsersFixture::TEST_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('form[name="dashboard_income"]')->count());
    }

    public function testDashboardShowsIncomeWidgetFromStoredRecords(): void
    {
        $this->loginAs(BaseUsersFixture::INCOMER_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Total income in GEL', $crawler->text(''));
        self::assertGreaterThan(
            0,
            $crawler->filter('a[href="/dashboard/incomes"]')->count()
        );
    }

    public function testIncomesPageShowsIncomeList(): void
    {
        $this->loginAs(BaseUsersFixture::INCOMER_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard/incomes');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Base EUR income', $crawler->text(''));
    }

    public function testDashboardIncomeTotalsAreSharedForAllUsers(): void
    {
        $redisStore = static::getContainer()->get(RedisStore::class);
        $redisStore->setJsonByDataKey(RedisDataKey::INCOME_RATES_LIVE, [], [
            'eurGel' => '3.000000',
            'usdtGel' => '2.700000',
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        $this->loginAs(BaseUsersFixture::ADMIN_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('250.00 GEL', $crawler->text(''));
    }

    public function testIncomeRefreshesMonthlyBalanceCacheAcrossDashboardAndIncomesPage(): void
    {
        $redisStore = static::getContainer()->get(RedisStore::class);
        $redisStore->setJsonByDataKey(RedisDataKey::INCOME_RATES_LIVE, [], [
            'eurGel' => '2.500000',
            'usdtGel' => '2.700000',
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        $this->loginAs(BaseUsersFixture::INCOMER_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        $eurOption = $crawler->filterXPath(
            '//select[@name="dashboard_income[currency]"]'
            .'/option[contains(normalize-space(.), "EUR")]'
        )->first();
        self::assertSame(1, $eurOption->count());
        $eurValue = (string) $eurOption->attr('value');

        $form = $crawler->selectButton('Add income')->form([
            'dashboard_income[amount]' => '10',
            'dashboard_income[currency]' => $eurValue,
            'dashboard_income[comment]' => 'Monthly cache income',
            'dashboard_income[convertToGel]' => 1,
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/dashboard');

        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('25.00 GEL', $crawler->text(''));

        $crawler = $this->client->request('GET', '/dashboard/incomes');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('25.00 GEL', $crawler->text(''));

        $snapshot = $redisStore->getJsonByDataKey(
            RedisDataKey::MONTHLY_BALANCE_SNAPSHOT,
            ['monthKey' => (new \DateTimeImmutable())->format('Y-m')]
        );
        self::assertIsArray($snapshot);
        self::assertSame('25.00', (string) ($snapshot['totalIncomeGel'] ?? ''));
    }
}
