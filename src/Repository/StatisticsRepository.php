<?php

namespace App\Repository;

use App\Entity\Statistics;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Statistics>
 */
class StatisticsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Statistics::class);
    }

    public function save(Statistics $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Statistics $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.type = :type')
            ->setParameter('type', $type)
            ->orderBy('s.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(\DateTime $startDate, \DateTime $endDate, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.date >= :startDate')
            ->andWhere('s.date <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('s.date', 'ASC');

        if ($type) {
            $qb->andWhere('s.type = :type')
               ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    public function findLatestByType(string $type, int $limit = 30): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.type = :type')
            ->setParameter('type', $type)
            ->orderBy('s.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findTodayByType(string $type): ?Statistics
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        
        return $this->createQueryBuilder('s')
            ->andWhere('s.type = :type')
            ->andWhere('s.date = :today')
            ->setParameter('type', $type)
            ->setParameter('today', $today)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getTotalCount(string $type): int
    {
        $result = $this->createQueryBuilder('s')
            ->select('SUM(s.count)')
            ->andWhere('s.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : 0;
    }
}
