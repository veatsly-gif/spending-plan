<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Income;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Income>
 */
class IncomeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Income::class);
    }

    public function save(Income $income, bool $flush = false): void
    {
        $this->getEntityManager()->persist($income);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Income $income, bool $flush = false): void
    {
        $this->getEntityManager()->remove($income);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findLastByUser(User $user): ?Income
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.currency', 'currency')
            ->addSelect('currency')
            ->andWhere('i.userAdded = :user')
            ->setParameter('user', $user)
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Income>
     */
    public function findForMonth(\DateTimeImmutable $monthStart): array
    {
        $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('i')
            ->leftJoin('i.currency', 'currency')
            ->addSelect('currency')
            ->leftJoin('i.userAdded', 'userAdded')
            ->addSelect('userAdded')
            ->andWhere('i.createdAt >= :start')
            ->andWhere('i.createdAt <= :end')
            ->setParameter('start', $monthStart->setTime(0, 0))
            ->setParameter('end', $monthEnd)
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLast(): ?Income
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.currency', 'currency')
            ->addSelect('currency')
            ->leftJoin('i.userAdded', 'userAdded')
            ->addSelect('userAdded')
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<string>
     */
    public function findMonthKeys(): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT DISTINCT TO_CHAR(created_at, 'YYYY-MM') AS month_key FROM income ORDER BY month_key ASC"
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

    /**
     * @return list<Income>
     */
    public function findForMonthByUser(User $user, \DateTimeImmutable $monthStart): array
    {
        $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('i')
            ->leftJoin('i.currency', 'currency')
            ->addSelect('currency')
            ->andWhere('i.userAdded = :user')
            ->andWhere('i.createdAt >= :start')
            ->andWhere('i.createdAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $monthStart->setTime(0, 0))
            ->setParameter('end', $monthEnd)
            ->orderBy('i.createdAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Income>
     */
    public function findWithoutOfficialRatedAmountOlderThan(\DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.currency', 'currency')
            ->addSelect('currency')
            ->andWhere('i.officialRatedAmountInGel IS NULL')
            ->andWhere('i.createdAt <= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('i.createdAt', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Income>
     */
    public function findWithoutAmountInGel(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.currency', 'currency')
            ->addSelect('currency')
            ->andWhere('i.amountInGel IS NULL')
            ->orderBy('i.createdAt', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
