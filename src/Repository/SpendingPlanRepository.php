<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SpendingPlan;
use App\SpendingPlan\SpendingPlanDisplaySorter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SpendingPlan>
 */
class SpendingPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SpendingPlan::class);
    }

    public function save(SpendingPlan $spendingPlan, bool $flush = false): void
    {
        $this->getEntityManager()->persist($spendingPlan);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SpendingPlan $spendingPlan, bool $flush = false): void
    {
        $this->getEntityManager()->remove($spendingPlan);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<SpendingPlan>
     */
    public function findByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('sp')
            ->andWhere('sp.dateFrom >= :from')
            ->andWhere('sp.dateTo <= :to')
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->orderBy('sp.dateFrom', 'ASC')
            ->addOrderBy('sp.weight', 'DESC')
            ->addOrderBy('sp.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existsSystemPlanSignature(
        string $planType,
        string $name,
        \DateTimeImmutable $dateFrom,
        \DateTimeImmutable $dateTo,
    ): bool {
        $count = $this->createQueryBuilder('sp')
            ->select('COUNT(sp.id)')
            ->andWhere('sp.isSystem = :isSystem')
            ->andWhere('sp.planType = :planType')
            ->andWhere('sp.name = :name')
            ->andWhere('sp.dateFrom = :dateFrom')
            ->andWhere('sp.dateTo = :dateTo')
            ->setParameter('isSystem', true)
            ->setParameter('planType', $planType)
            ->setParameter('name', $name)
            ->setParameter('dateFrom', $dateFrom->setTime(0, 0))
            ->setParameter('dateTo', $dateTo->setTime(0, 0))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function countSystemPlansForPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $count = $this->createQueryBuilder('sp')
            ->select('COUNT(sp.id)')
            ->andWhere('sp.isSystem = :isSystem')
            ->andWhere('sp.dateFrom >= :from')
            ->andWhere('sp.dateTo <= :to')
            ->setParameter('isSystem', true)
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('to', $to->setTime(0, 0))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @return list<SpendingPlan>
     */
    public function findForMonth(\DateTimeImmutable $monthStart, \DateTimeImmutable $monthEnd): array
    {
        $plans = $this->createQueryBuilder('sp')
            ->andWhere('sp.dateFrom <= :monthEnd')
            ->andWhere('sp.dateTo >= :monthStart')
            ->setParameter('monthStart', $monthStart->setTime(0, 0))
            ->setParameter('monthEnd', $monthEnd->setTime(0, 0))
            ->getQuery()
            ->getResult();

        return SpendingPlanDisplaySorter::sort($plans);
    }

    public function countForMonth(\DateTimeImmutable $monthStart, \DateTimeImmutable $monthEnd): int
    {
        $count = $this->createQueryBuilder('sp')
            ->select('COUNT(sp.id)')
            ->andWhere('sp.dateFrom <= :monthEnd')
            ->andWhere('sp.dateTo >= :monthStart')
            ->setParameter('monthStart', $monthStart->setTime(0, 0))
            ->setParameter('monthEnd', $monthEnd->setTime(0, 0))
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count;
    }

    /**
     * @deprecated Use findForSpendSelection — spend UI lists all plans overlapping the calendar month of the spend date.
     */
    public function findForDate(\DateTimeImmutable $date): array
    {
        return $this->findForSpendSelection($date);
    }

    /**
     * Plans overlapping the calendar month of {@see $date} (same set as admin spending-plan list for that month).
     * Order: higher {@see SpendingPlan::weight} first; if equal, regular/planned (date-based limits) before other types;
     * then {@see SpendingPlan::getDateFrom()}, then id.
     *
     * @return list<SpendingPlan>
     */
    public function findForSpendSelection(\DateTimeImmutable $date): array
    {
        $d = $date->setTime(0, 0);
        $monthStart = $d->modify('first day of this month');
        $monthEnd = $monthStart->modify('last day of this month');

        return $this->findForMonth($monthStart, $monthEnd);
    }

    public function findBestForDate(\DateTimeImmutable $date): ?SpendingPlan
    {
        $day = $date->setTime(0, 0);
        /** @var list<SpendingPlan> $candidates */
        $candidates = $this->createQueryBuilder('sp')
            ->andWhere('sp.dateFrom <= :date')
            ->andWhere('sp.dateTo >= :date')
            ->setParameter('date', $day)
            ->getQuery()
            ->getResult();

        if ([] === $candidates) {
            return null;
        }

        $sorted = SpendingPlanDisplaySorter::sort($candidates);

        return $sorted[0] ?? null;
    }
}
