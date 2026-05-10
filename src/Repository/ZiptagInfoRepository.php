<?php

namespace App\Repository;

use App\Entity\ZiptagInfo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ZiptagInfo>
 */
class ZiptagInfoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZiptagInfo::class);
    }

    public function findRecentByCollar($collarId, int $limit = 20)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.ziptagInfo = :collar')
            ->setParameter('collar', $collarId)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type)
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.type = :type')
            ->setParameter('type', $type)
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
