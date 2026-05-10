<?php

namespace App\Repository;


use App\Entity\Dogs;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dogs>
 */
class DogsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dogs::class);
    }

    /**
     * @return Dogs[]
     */
    public function findWithoutZiptag(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.ziptag', 'z')
            ->andWhere('z.id IS NULL')
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
  
    /**
     * @return Dogs[]
     */
    public function findUnassignedOrCurrent($currentDogId = null)
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.ziptag', 'z')
            ->where('z.id IS NULL')
            ->andWhere('d.adoption_status = :streetdog')
            ->setParameter('streetdog', 'Streetdog');

        if ($currentDogId) {
            $qb->orWhere('d.id = :currentId')
               ->setParameter('currentId', $currentDogId);
        }

        return $qb->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
  
public function searchDogs(string $searchValue): array
{
    return $this->createQueryBuilder('d')
        ->where('d.name LIKE :searchValue')
        ->orWhere('d.breed LIKE :searchValue')
        ->orWhere('d.microchip_number LIKE :searchValue')
        ->setParameter('searchValue', '%' . $searchValue . '%')
        ->getQuery()
        ->getResult();
}



    public function filterDogs(?string $adoptionStatus = null, ?string $healthStatus = null, ?string $gender = null): array
    {
        $qb = $this->createQueryBuilder('d');
        if ($adoptionStatus !== null && $adoptionStatus !== '') {
            $qb->andWhere('d.adoption_status = :adoptionStatus')
               ->setParameter('adoptionStatus', $adoptionStatus);
        }
        if ($healthStatus !== null && $healthStatus !== '') {
            $qb->andWhere('d.health_status = :healthStatus')
               ->setParameter('healthStatus', $healthStatus);
        }
        if ($gender !== null && $gender !== '') {
            $qb->andWhere('d.gender = :gender')
               ->setParameter('gender', $gender);
        }
        return $qb->orderBy('d.name', 'ASC')->getQuery()->getResult();
    }

    //    /**
    //     * @return Dogs[] Returns an array of Dogs objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('d.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Dogs
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
  