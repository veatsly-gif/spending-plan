<?php

declare(strict_types=1);

namespace App\Tests\Unit\SpendingPlan;

use App\Entity\Currency;
use App\Entity\SpendingPlan;
use App\SpendingPlan\SpendingPlanDisplaySorter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SpendingPlanDisplaySorterTest extends TestCase
{
    public function testSortsByWeightThenDateBasedTypeThenDateFrom(): void
    {
        $highCustom = $this->plan(1, 'high-custom', SpendingPlan::PLAN_TYPE_CUSTOM, 5, '2025-03-01', '2025-03-31');
        $lowRegular = $this->plan(2, 'low-regular', SpendingPlan::PLAN_TYPE_REGULAR, 1, '2025-03-01', '2025-03-31');
        $midRegular = $this->plan(3, 'mid-regular', SpendingPlan::PLAN_TYPE_REGULAR, 3, '2025-03-10', '2025-03-20');
        $midCustom = $this->plan(4, 'mid-custom', SpendingPlan::PLAN_TYPE_CUSTOM, 3, '2025-03-01', '2025-03-15');

        $sorted = SpendingPlanDisplaySorter::sort([$lowRegular, $highCustom, $midCustom, $midRegular]);

        self::assertSame(
            ['high-custom', 'mid-regular', 'mid-custom', 'low-regular'],
            array_map(static fn (SpendingPlan $p) => $p->getName(), $sorted)
        );
    }

    private function plan(
        int $id,
        string $name,
        string $planType,
        int $weight,
        string $from,
        string $to,
    ): SpendingPlan {
        $p = (new SpendingPlan())
            ->setName($name)
            ->setPlanType($planType)
            ->setWeight($weight)
            ->setCurrency((new Currency())->setCode('GEL'))
            ->setDateFrom(new \DateTimeImmutable($from))
            ->setDateTo(new \DateTimeImmutable($to));

        $ref = new ReflectionClass(SpendingPlan::class);
        $prop = $ref->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($p, $id);

        return $p;
    }
}
