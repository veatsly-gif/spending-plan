<?php

declare(strict_types=1);

namespace App\Tests\Unit\Triggers;

use App\Entity\User;
use App\Repository\SpendingPlanRepository;
use App\Service\RedisStore;
use App\Service\SpendingPlanSuggestionCacheService;
use App\Triggers\MissingNextMonthSpendingPlansTrigger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MissingNextMonthSpendingPlansTriggerTest extends TestCase
{
    public function testBuildsSuggestionsAndReturnsPayloadWhenMonthHasNoPlans(): void
    {
        $repository = $this->createSpendingPlanRepositoryMock();
        $repository
            ->expects($this->once())
            ->method('countForMonth')
            ->willReturn(0);

        $suggestions = new SpendingPlanSuggestionCacheService(new RedisStore('invalid-dsn'));
        $trigger = new MissingNextMonthSpendingPlansTrigger($repository, $suggestions);

        $now = new \DateTimeImmutable('2026-03-27 09:00:00');
        $monthKey = $now->modify('first day of next month')->format('Y-m');
        $suggestions->clearSuggestions($monthKey);

        $payload = $trigger->evaluate($this->buildAdminUser(), $now);

        self::assertIsArray($payload);
        self::assertSame($monthKey, $payload['monthKey'] ?? null);
        self::assertTrue($suggestions->hasSuggestions($monthKey));
    }

    public function testReturnsNullWhenPlansExistForNextMonth(): void
    {
        $repository = $this->createSpendingPlanRepositoryMock();
        $repository
            ->expects($this->once())
            ->method('countForMonth')
            ->willReturn(3);

        $suggestions = new SpendingPlanSuggestionCacheService(new RedisStore('invalid-dsn'));
        $trigger = new MissingNextMonthSpendingPlansTrigger($repository, $suggestions);

        $payload = $trigger->evaluate($this->buildAdminUser(), new \DateTimeImmutable('2026-03-27 09:00:00'));

        self::assertNull($payload);
    }

    private function buildAdminUser(): User
    {
        return (new User())
            ->setUsername('admin')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('hash');
    }

    /**
     * @return SpendingPlanRepository&MockObject
     */
    private function createSpendingPlanRepositoryMock(): SpendingPlanRepository
    {
        return $this
            ->getMockBuilder(SpendingPlanRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['countForMonth'])
            ->getMock();
    }
}
