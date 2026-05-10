<?php

namespace App\Repository;

use App\Entity\ObservationStation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ObservationStation>
 */
class ObservationStationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObservationStation::class);
    }

    public function findStationByCode(string $code): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.code LIKE :code')
            ->setParameter('code', '%' . $code . '%')
            ->getQuery()
            ->getResult();
    }

    public function findInactiveStations(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.statut = :statut')
            ->setParameter('statut', 'inactive')
            ->orderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
