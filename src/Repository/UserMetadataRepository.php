<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserMetadata;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserMetadata>
 */
class UserMetadataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserMetadata::class);
    }

    public function save(UserMetadata $metadata, bool $flush = false): void
    {
        $this->getEntityManager()->persist($metadata);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserMetadata $metadata, bool $flush = false): void
    {
        $this->getEntityManager()->remove($metadata);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUser(User $user): ?UserMetadata
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function findByUserId(int $userId): ?UserMetadata
    {
        return $this->createQueryBuilder('um')
            ->andWhere('um.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
