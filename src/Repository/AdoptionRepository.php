<?php

namespace App\Repository;

use App\Entity\Adoption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Adoption>
 */
class AdoptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Adoption::class);
    }

    /**
     * @return Adoption[]
     */
    public function searchAdoptions(string $query): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->leftJoin('a.dog', 'd')
            ->where('LOWER(u.nom) LIKE :q')
            ->orWhere('LOWER(u.prenom) LIKE :q')
            ->orWhere('LOWER(u.email) LIKE :q')
            ->orWhere('LOWER(d.name) LIKE :q')
            ->orWhere('LOWER(a.housingType) LIKE :q')
            ->setParameter('q', '%'.mb_strtolower($query).'%')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns adoption request counts grouped by dog, sorted by volume and recency.
     *
     * @return array<int, array{dog_id:int|string|null, requestsCount:int|string|null, lastRequestAt:\DateTimeInterface|string|null}>
     */
    public function findDogRequestSummary(): array
    {
        return $this->createQueryBuilder('a')
            ->select('IDENTITY(a.dog) AS dog_id, COUNT(a.id) AS requestsCount, MAX(a.createdAt) AS lastRequestAt')
            ->groupBy('a.dog')
            ->orderBy('requestsCount', 'DESC')
            ->addOrderBy('lastRequestAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Adoption[]
     */
    public function findByDogWithUserOrdered(\App\Entity\Dogs $dog): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->andWhere('a.dog = :dog')
            ->setParameter('dog', $dog)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
