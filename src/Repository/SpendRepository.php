<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Spend;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Spend>
 */
class SpendRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Spend::class);
    }

    public function save(Spend $spend, bool $flush = false): void
    {
        $this->getEntityManager()->persist($spend);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Spend $spend, bool $flush = false): void
    {
        $this->getEntityManager()->remove($spend);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return list<Spend>
     */
    public function findForMonth(\DateTimeImmutable $monthStart): array
    {
        $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('s')
            ->leftJoin('s.currency', 'currency')
            ->addSelect('currency')
            ->leftJoin('s.userAdded', 'userAdded')
            ->addSelect('userAdded')
            ->leftJoin('s.spendingPlan', 'spendingPlan')
            ->addSelect('spendingPlan')
            ->andWhere('s.spendDate >= :start')
            ->andWhere('s.spendDate <= :end')
            ->setParameter('start', $monthStart->setTime(0, 0))
            ->setParameter('end', $monthEnd)
            ->orderBy('s.spendDate', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLast(): ?Spend
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.currency', 'currency')
            ->addSelect('currency')
            ->leftJoin('s.userAdded', 'userAdded')
            ->addSelect('userAdded')
            ->leftJoin('s.spendingPlan', 'spendingPlan')
            ->addSelect('spendingPlan')
            ->orderBy('s.spendDate', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Spend>
     */
    public function findForUserInPeriod(
        User $user,
        \DateTimeImmutable $fromDate,
        \DateTimeImmutable $toDate,
    ): array {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.currency', 'currency')
            ->addSelect('currency')
            ->andWhere('s.userAdded = :user')
            ->andWhere('s.spendDate >= :from')
            ->andWhere('s.spendDate <= :to')
            ->setParameter('user', $user)
            ->setParameter('from', $fromDate->setTime(0, 0))
            ->setParameter('to', $toDate->setTime(0, 0))
            ->orderBy('s.spendDate', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{latestId: int, total: int}
     */
    public function findMonthVersion(\DateTimeImmutable $monthStart): array
    {
        $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);

        /** @var array{latestId: string|int|null, total: string|int|null} $row */
        $row = $this->createQueryBuilder('s')
            ->select('MAX(s.id) AS latestId, COUNT(s.id) AS total')
            ->andWhere('s.spendDate >= :start')
            ->andWhere('s.spendDate <= :end')
            ->setParameter('start', $monthStart->setTime(0, 0))
            ->setParameter('end', $monthEnd)
            ->getQuery()
            ->getSingleResult();

        return [
            'latestId' => null !== $row['latestId'] ? (int) $row['latestId'] : 0,
            'total' => null !== $row['total'] ? (int) $row['total'] : 0,
        ];
    }

    /**
     * @return list<string>
     */
    public function findMonthKeys(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT DISTINCT TO_CHAR(spend_date, 'YYYY-MM') AS month_key FROM spend ORDER BY month_key ASC"
        );

        $keys = [];
        foreach ($rows as $row) {
            $monthKey = isset($row['month_key']) ? trim((string) $row['month_key']) : '';
            if (1 !== preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
                continue;
            }

            $keys[] = $monthKey;
        }

        return $keys;
    }
}
