<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Income\IncomeRateService;
use App\Service\RedisStore;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class IncomeRateServiceTest extends TestCase
{
    private RedisStore $redisStore;

    protected function setUp(): void
    {
        $this->redisStore = new RedisStore('redis://invalid-host:6380');
        $this->redisStore->delete('income:rates:live');
    }

    public function testRefreshLiveRatesStoresDataInRedis(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) json_encode([
                [
                    'currencies' => [
                        [
                            'code' => 'EUR',
                            'quantity' => 1,
                            'rate' => 3.1000,
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse((string) json_encode([
                'tether' => [
                    'gel' => 2.7400,
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $service = new IncomeRateService($client, $this->redisStore, new NullLogger());
        $now = new \DateTimeImmutable('2026-03-27 10:00:00');

        $rates = $service->refreshLiveRates($now);
        self::assertNotNull($rates);
        self::assertSame('3.100000', $rates->eurGel);
        self::assertSame('2.740000', $rates->usdtGel);

        $fromCache = $service->getLiveRates();
        self::assertNotNull($fromCache);
        self::assertSame('3.100000', $fromCache->eurGel);
        self::assertSame('2.740000', $fromCache->usdtGel);
    }

    public function testConvertAmountToGelUsesCachedRates(): void
    {
        $this->redisStore->set('income:rates:live', (string) json_encode([
            'eurGel' => '3.200000',
            'usdtGel' => '2.800000',
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR));

        $service = new IncomeRateService(
            new MockHttpClient([]),
            $this->redisStore,
            new NullLogger()
        );

        self::assertSame('32.00', $service->convertAmountToGel('10', 'EUR'));
        self::assertSame('28.00', $service->convertAmountToGel('10', 'USDT'));
        self::assertSame('10.00', $service->convertAmountToGel('10', 'GEL'));
    }

    public function testOfficialRateForUsdtUsesUsdRateWithFallbackDay(): void
    {
        $client = new MockHttpClient([
            new MockResponse((string) json_encode([], JSON_THROW_ON_ERROR)),
            new MockResponse((string) json_encode([
                [
                    'currencies' => [
                        [
                            'code' => 'USD',
                            'quantity' => 1,
                            'rate' => 2.7000,
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $service = new IncomeRateService($client, $this->redisStore, new NullLogger());

        $rate = $service->getOfficialGelRateForDate(
            'USDT',
            new \DateTimeImmutable('2026-03-23')
        );

        self::assertSame('2.700000', $rate);
    }
}
