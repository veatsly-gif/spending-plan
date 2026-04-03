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
            'testIncomesPageSupportsFiltersAndPagination' => [
                BaseCurrenciesFixture::class,
                BaseUsersFixture::class,
                BaseIncomesFixture::class,
            ],
            'testUserCanEditIncomeFromIncomesPage' => [
                BaseCurrenciesFixture::class,
                BaseUsersFixture::class,
                BaseIncomesFixture::class,
            ],
            'testUserCanDeleteIncomeFromIncomesPage' => [
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

    public function testIncomerSeesIncomeModalTriggersOnDashboardAndIncomesPage(): void
    {
        $this->loginAs(BaseUsersFixture::INCOMER_USERNAME);

        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-income-modal-open]')->count());
        self::assertGreaterThan(0, $crawler->filter('[data-income-modal] form[name="dashboard_income"]')->count());

        $crawler = $this->client->request('GET', '/dashboard/incomes');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-income-modal-open]')->count());
        self::assertGreaterThan(0, $crawler->filter('[data-income-modal] form[name="dashboard_income"]')->count());
    }

    public function testAdminSeesIncomeModalTriggersOnDashboardAndIncomesPage(): void
    {
        $this->loginAs(BaseUsersFixture::ADMIN_USERNAME);

        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-income-modal-open]')->count());
        self::assertGreaterThan(0, $crawler->filter('[data-income-modal] form[name="dashboard_income"]')->count());

        $crawler = $this->client->request('GET', '/dashboard/incomes');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[data-income-modal-open]')->count());
        self::assertGreaterThan(0, $crawler->filter('[data-income-modal] form[name="dashboard_income"]')->count());
    }

    public function testAdminCanAddIncomeUsingDashboardIncomeForm(): void
    {
        $this->loginAs(BaseUsersFixture::ADMIN_USERNAME);
        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        $gelOption = $crawler->filterXPath(
            '//select[@name="dashboard_income[currency]"]'
            .'/option[contains(normalize-space(.), "GEL")]'
        )->first();
        self::assertSame(1, $gelOption->count());
        $gelValue = (string) $gelOption->attr('value');

        $form = $crawler->selectButton('Add income')->form([
            'dashboard_income[amount]' => '15.00',
            'dashboard_income[currency]' => $gelValue,
            'dashboard_income[comment]' => 'Admin modal income',
            'dashboard_income[convertToGel]' => 1,
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/dashboard');

        $this->entityManager->clear();
        $income = $this->entityManager->getRepository(Income::class)->findOneBy([
            'comment' => 'Admin modal income',
        ]);
        self::assertInstanceOf(Income::class, $income);
        self::assertSame('15.00', $income->getAmount());
        self::assertSame('15.00', $income->getAmountInGel());
        self::assertSame('1.0000', $income->getRate());
        self::assertSame('admin', $income->getUserAdded()?->getUsername());
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

    public function testIncomesPageSupportsFiltersAndPagination(): void
    {
        $this->loginAs(BaseUsersFixture::INCOMER_USERNAME);

        $crawler = $this->client->request('GET', '/dashboard/incomes');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Base EUR income', $crawler->text(''));
        self::assertStringContainsString('Base GEL income', $crawler->text(''));

        $crawler = $this->client->request('GET', '/dashboard/incomes?q=EUR');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Base EUR income', $crawler->text(''));
        self::assertStringNotContainsString('Base GEL income', $crawler->text(''));

        $crawler = $this->client->request('GET', '/dashboard/incomes?perPage=1&page=2');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Base GEL income', $crawler->text(''));
        self::assertStringNotContainsString('Base EUR income', $crawler->text(''));
    }

    public function testUserCanEditIncomeFromIncomesPage(): void
    {
        $this->loginAs(BaseUsersFixture::INCOMER_USERNAME);

        $income = $this->entityManager->getRepository(Income::class)->findOneBy(['comment' => 'Base GEL income']);
        self::assertInstanceOf(Income::class, $income);

        $crawler = $this->client->request('GET', '/dashboard/incomes/'.$income->getId().'/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form([
            'dashboard_income[amount]' => '120.00',
            'dashboard_income[currency]' => (string) $income->getCurrency()?->getId(),
            'dashboard_income[comment]' => 'Base GEL income updated',
            'dashboard_income[convertToGel]' => 1,
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/dashboard/incomes?month='.(new \DateTimeImmutable('first day of this month'))->format('Y-m'));

        $this->entityManager->clear();
        $updatedIncome = $this->entityManager->getRepository(Income::class)->find($income->getId());
        self::assertInstanceOf(Income::class, $updatedIncome);
        self::assertSame('120.00', $updatedIncome->getAmount());
        self::assertSame('120.00', $updatedIncome->getAmountInGel());
        self::assertSame('1.0000', $updatedIncome->getRate());
        self::assertSame('Base GEL income updated', $updatedIncome->getComment());
    }

    public function testUserCanDeleteIncomeFromIncomesPage(): void
    {
        $this->loginAs(BaseUsersFixture::INCOMER_USERNAME);

        $income = $this->entityManager->getRepository(Income::class)->findOneBy(['comment' => 'Base EUR income']);
        self::assertInstanceOf(Income::class, $income);

        $crawler = $this->client->request('GET', '/dashboard/incomes');
        self::assertResponseIsSuccessful();

        $deleteForm = $crawler->filter(sprintf('form[action="/dashboard/incomes/%d/delete"]', $income->getId()))->first();
        self::assertSame(1, $deleteForm->count());
        $csrf = (string) $deleteForm->filter('input[name="_token"]')->attr('value');
        self::assertNotSame('', $csrf);

        $this->client->request('POST', '/dashboard/incomes/'.$income->getId().'/delete', [
            '_token' => $csrf,
        ]);

        self::assertResponseRedirects('/dashboard/incomes?month='.(new \DateTimeImmutable('first day of this month'))->format('Y-m'));

        $this->entityManager->clear();
        $removedIncome = $this->entityManager->getRepository(Income::class)->find($income->getId());
        self::assertNull($removedIncome);
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
