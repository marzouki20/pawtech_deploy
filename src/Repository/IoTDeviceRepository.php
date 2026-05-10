<?php

namespace App\Repository;

use App\Entity\IoTDevice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IoTDevice>
 */
class IoTDeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IoTDevice::class);
    }

    /**
     * Find all devices for a station
     */
    public function findByStationId(int $stationId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.station = :stationId')
            ->setParameter('stationId', $stationId)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find device by device ID
     */
    public function findByDeviceId(string $deviceId): ?IoTDevice
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.deviceId = :deviceId')
            ->setParameter('deviceId', $deviceId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active devices for a station
     */
    public function findActiveByStationId(int $stationId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.station = :stationId')
            ->andWhere('d.status = :status')
            ->setParameter('stationId', $stationId)
            ->setParameter('status', 'active')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find devices that haven't sent heartbeat within interval
     */
    public function findStaleDevices(int $timeoutSeconds = 600): array
    {
        $cutoff = new \DateTime("-{$timeoutSeconds} seconds");
        
        return $this->createQueryBuilder('d')
            ->andWhere('d.lastHeartbeat < :cutoff')
            ->orWhere('d.lastHeartbeat IS NULL')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }
}
