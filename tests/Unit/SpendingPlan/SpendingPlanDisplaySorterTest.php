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
    public function testSortsByBusinessPriorityForReferenceDay(): void
    {
        $birthday = $this->plan(1, "Dima's birthday", SpendingPlan::PLAN_TYPE_CUSTOM, 2, '2026-04-03', '2026-04-03');
        $friday = $this->plan(2, 'Пятничка', SpendingPlan::PLAN_TYPE_WEEKDAY, 1, '2026-04-03', '2026-04-03');
        $regular = $this->plan(3, 'Regular spends', SpendingPlan::PLAN_TYPE_REGULAR, 1, '2026-04-01', '2026-04-30');
        $planned = $this->plan(4, 'Planned spends', SpendingPlan::PLAN_TYPE_PLANNED, 1, '2026-04-01', '2026-04-30');
        $futureWeekend = $this->plan(5, 'Выхи 4-5 апреля', SpendingPlan::PLAN_TYPE_WEEKEND, 1, '2026-04-04', '2026-04-05');
        $futureWeekday = $this->plan(6, 'Будни 6-10 апреля', SpendingPlan::PLAN_TYPE_WEEKDAY, 1, '2026-04-06', '2026-04-10');
        $pastWeekday = $this->plan(7, 'Будни 1-2 апреля', SpendingPlan::PLAN_TYPE_WEEKDAY, 1, '2026-04-01', '2026-04-02');

        $sorted = SpendingPlanDisplaySorter::sort([
            $futureWeekend,
            $regular,
            $pastWeekday,
            $planned,
            $futureWeekday,
            $birthday,
            $friday,
        ], new \DateTimeImmutable('2026-04-03'));

        self::assertSame(
            [
                "Dima's birthday",
                'Пятничка',
                'Regular spends',
                'Planned spends',
                'Выхи 4-5 апреля',
                'Будни 6-10 апреля',
                'Будни 1-2 апреля',
            ],
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
