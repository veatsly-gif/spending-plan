<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Spend;
use App\Redis\RedisDataKey;
use App\Service\RedisStore;
use App\Tests\Fixtures\BaseCurrenciesFixture;
use App\Tests\Fixtures\BaseSpendsFixture;
use App\Tests\Fixtures\BaseUsersFixture;
use App\Tests\Fixtures\CurrentMonthSpendingPlanFixture;
use App\Tests\Functional\DatabaseWebTestCase;

/**
 * Dashboard spend flows are exercised through the JSON API and SPA redirects.
 */
final class DashboardSpendControllerTest extends DatabaseWebTestCase
{
    /**
     * @return list<class-string<\App\Tests\Fixtures\DatabaseFixtureInterface>>
     */
    protected function getFixturesForTest(string $testName): array
    {
        return match ($testName) {
            'testSpendListApiReturnsRowsWhenSpendsExist' => [
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

    public function testDashboardGetRedirectsToSpa(): void
    {
        $this->loginAs(BaseUsersFixture::TEST_USERNAME);
        $this->client->request('GET', '/dashboard');

        self::assertResponseRedirects('/app/dashboard');
    }

    public function testSpendsIndexGetRedirectsToSpaWithQuery(): void
    {
        $this->loginAs(BaseUsersFixture::TEST_USERNAME);
        $this->client->request('GET', '/dashboard/spends?month=2025-01&view=table');

        self::assertResponseRedirects('/app/dashboard/spends?month=2025-01&view=table');
    }

    public function testUserCanCreateSpendThroughApiAndSnapshotUpdates(): void
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
                'amount' => '37.20',
                'currencyId' => $currencyId,
                'spendingPlanId' => $spendingPlanId,
                'spendDate' => (string) ($defaults['spendDate'] ?? (new \DateTimeImmutable('today'))->format('Y-m-d')),
                'comment' => 'Coffee and lunch',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();

        $this->entityManager->clear();
        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy(['comment' => 'Coffee and lunch']);
        self::assertInstanceOf(Spend::class, $spend);
        self::assertSame('37.20', $spend->getAmount());

        $redisStore = static::getContainer()->get(RedisStore::class);
        $snapshot = $redisStore->getJsonByDataKey(
            RedisDataKey::MONTHLY_BALANCE_SNAPSHOT,
            ['monthKey' => (new \DateTimeImmutable())->format('Y-m')]
        );
        self::assertIsArray($snapshot);
        self::assertSame('37.20', (string) ($snapshot['monthSpentGel'] ?? ''));
    }

    public function testSpendListApiReturnsRowsWhenSpendsExist(): void
    {
        $token = $this->loginViaApi(BaseUsersFixture::TEST_USERNAME, BaseUsersFixture::TEST_PASSWORD);
        $this->client->request('GET', '/api/dashboard/spends', [], [], $this->bearerHeaders($token));
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($payload['success'] ?? false));
        self::assertNotEmpty($payload['payload']['spends'] ?? []);
    }

    public function testUserCanDeleteSpendThroughApi(): void
    {
        $token = $this->loginViaApi(BaseUsersFixture::TEST_USERNAME, BaseUsersFixture::TEST_PASSWORD);

        $this->client->request('GET', '/api/dashboard', [], [], $this->bearerHeaders($token));
        $overview = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $defaults = $overview['payload']['forms']['spend']['defaults'] ?? [];

        $this->client->request(
            'POST',
            '/api/dashboard/spends',
            [],
            [],
            array_merge($this->bearerHeaders($token), ['CONTENT_TYPE' => 'application/json']),
            json_encode([
                'amount' => '1.00',
                'currencyId' => (int) ($defaults['currencyId'] ?? 0),
                'spendingPlanId' => (int) ($defaults['spendingPlanId'] ?? 0),
                'spendDate' => (string) ($defaults['spendDate'] ?? (new \DateTimeImmutable('today'))->format('Y-m-d')),
                'comment' => 'api delete target',
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();

        $this->entityManager->clear();
        $spend = $this->entityManager->getRepository(Spend::class)->findOneBy(['comment' => 'api delete target']);
        self::assertInstanceOf(Spend::class, $spend);
        $id = (int) $spend->getId();

        $this->client->request('DELETE', '/api/dashboard/spends/'.$id, [], [], $this->bearerHeaders($token));
        self::assertResponseIsSuccessful();

        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Spend::class)->find($id));
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
