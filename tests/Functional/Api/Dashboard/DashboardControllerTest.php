<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Dashboard;

use App\Entity\Currency;
use App\Entity\Income;
use App\Entity\Spend;
use App\Tests\Fixtures\BaseCurrenciesFixture;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Fixtures\CurrentMonthSpendingPlanFixture;
use App\Tests\Functional\DatabaseWebTestCase;

final class DashboardControllerTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return [
            BaseCurrenciesFixture::class,
            BaseUsersFixture::class,
            CurrentMonthSpendingPlanFixture::class,
        ];
    }

    public function testOverviewReturnsPayloadForRegularUser(): void
    {
        $token = $this->loginViaApi(BaseUsersFixture::TEST_USERNAME, BaseUsersFixture::TEST_PASSWORD);
        $this->client->request('GET', '/api/dashboard', [], [], $this->bearerHeaders($token));

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($payload['success'] ?? false));
        self::assertIsArray($payload['payload'] ?? null);
        self::assertIsArray($payload['payload']['forms']['spend']['spendingPlans'] ?? null);
        self::assertNotEmpty($payload['payload']['forms']['spend']['spendingPlans'] ?? []);
        self::assertNull($payload['payload']['forms']['income'] ?? null);
    }

    public function testUserCanCreateSpendThroughApi(): void
    {
        $token = $this->loginViaApi(BaseUsersFixture::TEST_USERNAME, BaseUsersFixture::TEST_PASSWORD);

        $this->client->request('GET', '/api/dashboard', [], [], $this->bearerHeaders($token));
        self::assertResponseIsSuccessful();
        $overview = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $defaults = $overview['payload']['forms']['spend']['defaults'] ?? [];
        $currencyId = (int) ($defaults['currencyId'] ?? 0);
        $spendingPlanId = (int) ($defaults['spendingPlanId'] ?? 0);
        self::assertGreaterThan(0, $currencyId);
        self::assertGreaterThan(0, $spendingPlanId);

        $this->client->request(
            'POST',
            '/api/dashboard/spends',
            [],
            [],
            array_merge($this->bearerHeaders($token), [
                'CONTENT_TYPE' => 'application/json',
            ]),
            json_encode([
                'amount' => '12.34',
                'currencyId' => $currencyId,
                'spendingPlanId' => $spendingPlanId,
                'spendDate' => (string) ($defaults['spendDate'] ?? (new \DateTimeImmutable())->format('Y-m-d')),
                'comment' => 'React API spend',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($response['success'] ?? false));

        $this->entityManager->clear();
        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy(['comment' => 'React API spend']);
        self::assertInstanceOf(Spend::class, $spend);
        self::assertSame('12.34', $spend->getAmount());
    }

    public function testIncomerCanCreateIncomeThroughApi(): void
    {
        $token = $this->loginViaApi(BaseUsersFixture::INCOMER_USERNAME, BaseUsersFixture::INCOMER_PASSWORD);

        $currency = $this->entityManager->getRepository(Currency::class)->findOneBy(['code' => 'GEL']);
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
                'amount' => '44.40',
                'currencyId' => (int) $currency->getId(),
                'comment' => 'React API income',
                'convertToGel' => true,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($response['success'] ?? false));

        $this->entityManager->clear();
        $income = $this->entityManager->getRepository(Income::class)->findOneBy(['comment' => 'React API income']);
        self::assertInstanceOf(Income::class, $income);
        self::assertSame('44.40', $income->getAmount());
    }

    public function testRegularUserCannotCreateIncomeThroughApi(): void
    {
        $token = $this->loginViaApi(BaseUsersFixture::TEST_USERNAME, BaseUsersFixture::TEST_PASSWORD);

        $this->client->request(
            'POST',
            '/api/dashboard/incomes',
            [],
            [],
            array_merge($this->bearerHeaders($token), [
                'CONTENT_TYPE' => 'application/json',
            ]),
            json_encode([
                'amount' => '12.00',
                'currencyId' => 1,
                'comment' => 'Denied income',
                'convertToGel' => false,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(403);
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse((bool) ($response['success'] ?? true));
    }

    private function loginViaApi(string $username, string $password): string
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'username' => $username,
            'password' => $password,
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $token = isset($payload['token']) ? (string) $payload['token'] : '';
        self::assertNotSame('', $token);

        return $token;
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
