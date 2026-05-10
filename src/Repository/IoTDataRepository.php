<?php

namespace App\Repository;

use App\Entity\IoTData;
use App\Entity\ObservationStation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

/**
 * @extends ServiceEntityRepository<IoTData>
 */
class IoTDataRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IoTData::class);
    }

    public function save(IoTData $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(IoTData $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findLatestByStation(ObservationStation $station, int $limit = 10, int $hoursBack = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.station = :station')
            ->setParameter('station', $station);
        
        // Add time filter if hoursBack is specified
        if ($hoursBack !== null) {
            $qb->andWhere('i.createdAt >= :hoursAgo')
               ->setParameter('hoursAgo', new \DateTime("-{$hoursBack} seconds"));
        }
        
        return $qb
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find latest IoT data by station ID (integer)
     */
    public function findLatestByStationId(int $stationId, int $limit = 1): ?IoTData
    {
        $result = $this->createQueryBuilder('i')
            ->andWhere('i.station = :stationId')
            ->setParameter('stationId', $stationId)
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        
        return $result ? $result[0] : null;
    }

    /**
     * Find data newer than a given ID for a station
     */
    public function findNewerThan(int $stationId, int $afterId): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.station = :stationId')
            ->andWhere('i.id > :afterId')
            ->setParameter('stationId', $stationId)
            ->setParameter('afterId', $afterId)
            ->orderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestDataForAllStations(): array
    {
        $sql = '
            SELECT i.* FROM iot_data i
            INNER JOIN (
                SELECT station_id, MAX(created_at) as max_created
                FROM iot_data
                GROUP BY station_id
            ) latest ON i.station_id = latest.station_id AND i.created_at = latest.max_created
        ';
        
        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata(IoTData::class, 'i');
        
        return $this->getEntityManager()->createNativeQuery($sql, $rsm)->getResult();
    }

    public function findByDateRange(\DateTime $startDate, \DateTime $endDate, ?ObservationStation $station = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.createdAt >= :startDate')
            ->andWhere('i.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('i.createdAt', 'ASC');

        if ($station) {
            $qb->andWhere('i.station = :station')
               ->setParameter('station', $station);
        }

        return $qb->getQuery()->getResult();
    }

    public function getAverageTemperature(\DateTime $startDate, \DateTime $endDate): ?float
    {
        $result = $this->createQueryBuilder('i')
            ->select('AVG(i.temperature)')
            ->andWhere('i.createdAt >= :startDate')
            ->andWhere('i.createdAt <= :endDate')
            ->andWhere('i.temperature IS NOT NULL')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : null;
    }

    public function getAverageHumidity(\DateTime $startDate, \DateTime $endDate): ?float
    {
        $result = $this->createQueryBuilder('i')
            ->select('AVG(i.humidity)')
            ->andWhere('i.createdAt >= :startDate')
            ->andWhere('i.createdAt <= :endDate')
            ->andWhere('i.humidity IS NOT NULL')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : null;
    }

    public function findByStationId(int $stationId): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.station = :stationId')
            ->setParameter('stationId', $stationId)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function deleteOldData(\DateTime $before): int
    {
        return $this->createQueryBuilder('i')
            ->delete()
            ->andWhere('i.createdAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}