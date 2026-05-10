<?php

namespace App\Repository;

use App\Entity\Ziptag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ziptag>
 */
class ZiptagRepository extends ServiceEntityRepository
{



    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ziptag::class);
    }

    public function findBySerialNumber(string $serialNumber): ?Ziptag
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.serialNumber = :serial')
            ->setParameter('serial', $serialNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Ziptag[]
     */
    public function findUnassignedOrCurrent($currentZiptagId = null)
    {
        $qb = $this->createQueryBuilder('z')
            ->leftJoin('z.dog', 'd')
            ->where('d.id IS NULL');

        if ($currentZiptagId) {
            $qb->orWhere('z.id = :currentId')
               ->setParameter('currentId', $currentZiptagId);
        }

        return $qb->orderBy('z.serialNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //searchZiptags
public function searchZiptags(string $query): array
{
    return $this->createQueryBuilder('z')
        ->where('z.serialNumber LIKE :q')
        ->orWhere('z.model LIKE :q')
        ->orWhere('z.firmwareVersion LIKE :q')
        ->setParameter('q', '%' . $query . '%')
        ->getQuery()
        ->getResult();
}
//filterByfirmwareVersion and 
public function findByFilters(?string $firmwareVersion = null, ?bool $hasDog = null): array
{
    $qb = $this->createQueryBuilder('z');
    
    if ($firmwareVersion) {
        $qb->andWhere('z.firmwareVersion = :firmware')
           ->setParameter('firmware', $firmwareVersion);
    }
    
    if ($hasDog !== null) {
        if ($hasDog) {
            $qb->andWhere('z.dog IS NOT NULL');
        } else {
            $qb->andWhere('z.dog IS NULL');
        }
    }
    
    return $qb->getQuery()->getResult();
}
public function findDistinctFirmwareVersions(): array
{
    return $this->createQueryBuilder('z')
        ->select('DISTINCT z.firmwareVersion')
        ->orderBy('z.firmwareVersion', 'ASC')
        ->getQuery()
        ->getSingleColumnResult();
}
}
