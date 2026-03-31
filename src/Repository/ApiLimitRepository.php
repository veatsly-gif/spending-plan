<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiLimit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiLimit>
 */
class ApiLimitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiLimit::class);
    }

    public function save(ApiLimit $apiLimit, bool $flush = false): void
    {
        $this->getEntityManager()->persist($apiLimit);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findLatestByProvider(string $provider): ?ApiLimit
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.provider = :provider')
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
