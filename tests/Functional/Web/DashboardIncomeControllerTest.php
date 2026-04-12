<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Currency;
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
            'testIncomeListApiReturnsRowsWhenIncomesExist',
            'testIncomerCanDeleteIncomeThroughApi' => [
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

    public function testIncomesIndexGetRedirectsToSpa(): void
    {
        $this->loginAs(BaseUsersFixture::INCOMER_USERNAME);
        $this->client->request('GET', '/dashboard/incomes?month=2025-01');

        self::assertResponseRedirects('/app/dashboard/incomes?month=2025-01');
    }

    public function testIncomerCanAddIncomeWithConversionThroughApi(): void
    {
        $redisStore = static::getContainer()->get(RedisStore::class);
        $redisStore->setJsonByDataKey(RedisDataKey::INCOME_RATES_LIVE, [], [
            'eurGel' => '2.500000',
            'usdtGel' => '2.700000',
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        $token = $this->loginViaApi(BaseUsersFixture::INCOMER_USERNAME, BaseUsersFixture::INCOMER_PASSWORD);
        $currency = $this->entityManager->getRepository(Currency::class)->findOneBy(['code' => 'EUR']);
        self::assertInstanceOf(Currency::class, $currency);

        $this->client->request(
            'POST',
            '/api/dashboard/incomes',
            [],
            [],
            array_merge($this->bearerHeaders($token), [
                'CONTENT_TYPE' => 'application/json',
            ]),
            json_encode([
                'amount' => '100.00',
                'currencyId' => (int) $currency->getId(),
                'comment' => 'EUR salary',
                'convertToGel' => true,
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();

        $this->entityManager->clear();
        $income = $this->entityManager->getRepository(Income::class)->findOneBy(['comment' => 'EUR salary']);
        self::assertInstanceOf(Income::class, $income);
        self::assertSame('100.00', $income->getAmount());
        self::assertNotNull($income->getAmountInGel());
    }

    public function testIncomeListApiReturnsRowsWhenIncomesExist(): void
    {
        $token = $this->loginViaApi(BaseUsersFixture::INCOMER_USERNAME, BaseUsersFixture::INCOMER_PASSWORD);
        $this->client->request('GET', '/api/dashboard/incomes', [], [], $this->bearerHeaders($token));
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($payload['success'] ?? false));
        self::assertNotEmpty($payload['payload']['incomes'] ?? []);
    }

    public function testIncomerCanDeleteIncomeThroughApi(): void
    {
        $token = $this->loginViaApi(BaseUsersFixture::INCOMER_USERNAME, BaseUsersFixture::INCOMER_PASSWORD);
        $this->client->request('GET', '/api/dashboard/incomes', [], [], $this->bearerHeaders($token));
        $list = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $firstId = (int) (($list['payload']['incomes'][0]['id'] ?? 0));
        self::assertGreaterThan(0, $firstId);

        $this->client->request('DELETE', '/api/dashboard/incomes/'.$firstId, [], [], $this->bearerHeaders($token));
        self::assertResponseIsSuccessful();
    }

    private function loginViaApi(string $username, string $password): string
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'username' => $username,
            'password' => $password,
        ]);
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return (string) ($payload['token'] ?? '');
    }

    /**
     * @return array<string, string>
     */
    private function bearerHeaders(string $token): array
    {
        return [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ];
    }
}
